#!/usr/bin/env zsh
# FamilyTreeEditor - publish a signed artifact tarball for a mediawiki extension
# This file is licensed under the MIT License; See LICENSE for full text.
#
# usage: zsh scripts/publish-package.zsh --source-dir <dir> --name <name> --dest-dir <dir> \
#            [--version <string>] [--secret-key <path>] [--channel {stable,latest}]
#
# builds a shipping-clean tarball from a mediawiki extension source tree,
# signs it with minisign, then atomically publishes it (tarball + .minisig +
# a swung per-channel symlink pair) under <dest-dir>/<name>/. shared between
# this repo's extension/ and the sibling nova-discord repo - keep both
# copies identical.
#
# --channel selects which floating symlink pair gets swung, stable or
# latest (default: latest - the safe choice, promoting to stable needs the
# explicit flag). --channel stable requires $source_dir to be a clean git
# tree (no uncommitted tracked changes, staged or unstaged; untracked files
# are fine) before any build/sign work - mirrors
# doom-bot-dev/scripts/deploy.py's clean-tree convention. --channel latest
# skips that check entirely, since a dev-build channel needs to stay usable
# while iterating on uncommitted local changes.
#
# --secret-key overrides the signing key (default: ~/.minisign/minisign.key,
# password-protected in production). pass a throwaway unencrypted test key
# here for local verification runs; never commit a key produced that way.
set -eu
setopt pipefail

# --- Parse args --------------------------------------------------------

typeset -a SOURCE_DIR_OPT NAME_OPT DEST_DIR_OPT VERSION_OPT SECRET_KEY_OPT CHANNEL_OPT
zparseopts -D -E -F -- \
    -source-dir:=SOURCE_DIR_OPT \
    -name:=NAME_OPT \
    -dest-dir:=DEST_DIR_OPT \
    -version:=VERSION_OPT \
    -secret-key:=SECRET_KEY_OPT \
    -channel:=CHANNEL_OPT

if (( ! ${#SOURCE_DIR_OPT} || ! ${#NAME_OPT} || ! ${#DEST_DIR_OPT} )); then
    print -u2 -- "usage: publish-package.zsh --source-dir <dir> --name <name> --dest-dir <dir> [--version <string>] [--secret-key <path>] [--channel {stable,latest}]"
    exit 2
fi

source_dir="${SOURCE_DIR_OPT[2]:A}"
pkg_name="${NAME_OPT[2]}"
dest_dir="${DEST_DIR_OPT[2]:A}"
secret_key="${SECRET_KEY_OPT[2]:-$HOME/.minisign/minisign.key}"
channel="${CHANNEL_OPT[2]:-latest}"

case "$channel" in
    stable|latest) ;;
    *)
        print -u2 -- "invalid --channel: $channel (must be 'stable' or 'latest')"
        exit 2
        ;;
esac

[[ -d "$source_dir" ]] || { print -u2 -- "source dir not found: $source_dir"; exit 2; }
[[ -f "$secret_key" ]] || { print -u2 -- "secret key not found: $secret_key"; exit 2; }
command -v minisign >/dev/null || { print -u2 -- "missing: minisign"; exit 1; }
command -v rsync >/dev/null || { print -u2 -- "missing: rsync"; exit 1; }
command -v zstd >/dev/null || { print -u2 -- "missing: zstd"; exit 1; }
command -v git >/dev/null || { print -u2 -- "missing: git"; exit 1; }

# --- Channel gate: stable requires a clean tracked-file tree ------------
# mirrors doom-bot-dev/scripts/deploy.py's convention exactly: git status
# --porcelain, filter out untracked ("??") lines, abort on any remaining
# tracked-file dirty line (staged or unstaged both count). latest skips
# this entirely - a dev-build channel needs to stay usable while iterating
# on uncommitted local changes
if [[ "$channel" == "stable" ]]; then
    dirty_lines=("${(@f)$(git -C "$source_dir" status --porcelain)}")
    dirty_tracked=()
    for line in "${dirty_lines[@]}"; do
        [[ -z "$line" ]] && continue
        [[ "${line[1,2]}" == '??' ]] && continue
        dirty_tracked+=("$line")
    done
    if (( ${#dirty_tracked} )); then
        print -u2 -- "abort: $source_dir has uncommitted tracked changes, refusing --channel stable publish:"
        for line in "${dirty_tracked[@]}"; do
            print -u2 -- "  $line"
        done
        exit 1
    fi
fi

# fall back to extension.json's "version" field, then a UTC timestamp
if (( ${#VERSION_OPT} )); then
    version="${VERSION_OPT[2]}"
elif [[ -f "$source_dir/extension.json" ]]; then
    version="$(python3 -c "
import json
print(json.load(open('$source_dir/extension.json')).get('version', ''))
")"
fi
version="${version:-$(date -u +%Y%m%dT%H%M%SZ)}"

pkg_dir="$dest_dir/$pkg_name"
mkdir -p "$pkg_dir"

print -P "%F{cyan}[publish]%f $pkg_name $version <- $source_dir"

# --- Build: stage a shipping-clean copy of the source tree -------------
# excludes vcs/dev/test cruft that shouldn't reach a release tarball:
#   .git            - vcs metadata, never shipped
#   vendor          - composer install output, rebuilt by the consumer
#   node_modules    - never present for these php-only extensions, excluded defensively
#   composer.lock   - lockfile is dev/CI-time, not a runtime artifact
#   .dev/           - this repo's local docker dev-stack scaffolding
#   .phan/          - static-analysis config, dev-time only
#   tests/          - phpunit fixtures/suites, judged non-shipping for a production install
build_dir="$(mktemp -d)"
trap 'rm -rf "$build_dir"' EXIT

stage_dir="$build_dir/$pkg_name"
rsync --archive \
    --exclude='.git' \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='composer.lock' \
    --exclude='.dev' \
    --exclude='.phan' \
    --exclude='tests' \
    "$source_dir/" "$stage_dir/"

# --- Tar + sign into place-adjacent tmp files (same filesystem as the ---
# --- final destination, so the later move is a true atomic rename)    ---
tar_name="${pkg_name}-${version}.tar.zst"
tar_final="$pkg_dir/$tar_name"
sig_final="$tar_final.minisig"
tar_tmp="$pkg_dir/.${tar_name}.tmp.$$"
sig_tmp="$tar_tmp.minisig"
trap 'rm -rf "$build_dir" "$tar_tmp" "$sig_tmp"' EXIT

tar --create --zstd --file "$tar_tmp" --directory "$build_dir" "$pkg_name"

minisign -S -s "$secret_key" -x "$sig_tmp" -m "$tar_tmp" \
    -t "$pkg_name $version"

# --- Atomically move the signed pair into place ------------------------
# same-directory rename is atomic on a posix filesystem - no partial
# tarball or signature is ever visible at the final path
mv -- "$tar_tmp" "$tar_final"
mv -- "$sig_tmp" "$sig_final"

# --- Swing the per-channel symlink pair ---------------------------------
# built the same way: symlink into a tmp path, then atomically rename over
# the previous link so readers never see a dangling or half-updated target.
# each channel (stable/latest) swings its own independent pair - publishing
# one channel never touches the other's symlink
channel_tar="$pkg_dir/${pkg_name}-${channel}.tar.zst"
channel_sig="$channel_tar.minisig"
channel_tar_tmp="$channel_tar.tmp.$$"
channel_sig_tmp="$channel_sig.tmp.$$"

ln --symbolic -- "$tar_name" "$channel_tar_tmp"
ln --symbolic -- "$tar_name.minisig" "$channel_sig_tmp"
mv -- "$channel_tar_tmp" "$channel_tar"
mv -- "$channel_sig_tmp" "$channel_sig"

print -P "%F{green}[publish]%f done: $tar_final"
print -P "%F{green}[publish]%f $channel: $channel_tar"
