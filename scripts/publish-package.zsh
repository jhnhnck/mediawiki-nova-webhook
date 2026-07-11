#!/usr/bin/env zsh
# FamilyTreeEditor - publish a signed artifact tarball for a mediawiki extension
# This file is licensed under the MIT License; See LICENSE for full text.
#
# usage: zsh scripts/publish-package.zsh --source-dir <dir> --name <name> --dest-dir <dir> \
#            [--version <string>] [--secret-key <path>]
#
# builds a shipping-clean tarball from a mediawiki extension source tree,
# signs it with minisign, then atomically publishes it (tarball + .minisig +
# a swung "latest" symlink pair) under <dest-dir>/<name>/. shared between
# this repo's extension/ and the sibling nova-discord repo - keep both
# copies identical.
#
# --secret-key overrides the signing key (default: ~/.minisign/minisign.key,
# password-protected in production). pass a throwaway unencrypted test key
# here for local verification runs; never commit a key produced that way.
set -eu
setopt pipefail

# --- Parse args --------------------------------------------------------

typeset -a SOURCE_DIR_OPT NAME_OPT DEST_DIR_OPT VERSION_OPT SECRET_KEY_OPT
zparseopts -D -E -F -- \
    -source-dir:=SOURCE_DIR_OPT \
    -name:=NAME_OPT \
    -dest-dir:=DEST_DIR_OPT \
    -version:=VERSION_OPT \
    -secret-key:=SECRET_KEY_OPT

if (( ! ${#SOURCE_DIR_OPT} || ! ${#NAME_OPT} || ! ${#DEST_DIR_OPT} )); then
    print -u2 -- "usage: publish-package.zsh --source-dir <dir> --name <name> --dest-dir <dir> [--version <string>] [--secret-key <path>]"
    exit 2
fi

source_dir="${SOURCE_DIR_OPT[2]:A}"
pkg_name="${NAME_OPT[2]}"
dest_dir="${DEST_DIR_OPT[2]:A}"
secret_key="${SECRET_KEY_OPT[2]:-$HOME/.minisign/minisign.key}"

[[ -d "$source_dir" ]] || { print -u2 -- "source dir not found: $source_dir"; exit 2; }
[[ -f "$secret_key" ]] || { print -u2 -- "secret key not found: $secret_key"; exit 2; }
command -v minisign >/dev/null || { print -u2 -- "missing: minisign"; exit 1; }
command -v rsync >/dev/null || { print -u2 -- "missing: rsync"; exit 1; }
command -v zstd >/dev/null || { print -u2 -- "missing: zstd"; exit 1; }

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

# --- Swing the "latest" symlink pair ------------------------------------
# built the same way: symlink into a tmp path, then atomically rename over
# the previous link so readers never see a dangling or half-updated target
latest_tar="$pkg_dir/${pkg_name}-latest.tar.zst"
latest_sig="$latest_tar.minisig"
latest_tar_tmp="$latest_tar.tmp.$$"
latest_sig_tmp="$latest_sig.tmp.$$"

ln --symbolic -- "$tar_name" "$latest_tar_tmp"
ln --symbolic -- "$tar_name.minisig" "$latest_sig_tmp"
mv -- "$latest_tar_tmp" "$latest_tar"
mv -- "$latest_sig_tmp" "$latest_sig"

print -P "%F{green}[publish]%f done: $tar_final"
print -P "%F{green}[publish]%f latest: $latest_tar"
