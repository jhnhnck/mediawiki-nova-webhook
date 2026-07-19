#!/usr/bin/env zsh
# FamilyTreeEditor - publish a signed artifact tarball for a mediawiki extension
# This file is licensed under the MIT License; See LICENSE for full text.
#
# usage: zsh scripts/publish-package.zsh [--source-dir <dir>] [--name <name>] \
#            [--dest-dir <dir>] [--version <string>] [--secret-key <path>] \
#            [--channel {stable,latest}] [--build-cmd <command>] [--bump {minor,patch}]
#
# builds a shipping-clean tarball from a mediawiki extension source tree,
# signs it with minisign, then atomically publishes it (tarball + .minisig +
# a swung per-channel symlink pair) under <dest-dir>/<name>/. shared between
# this repo's extension/ and the sibling nova-discord repo - keep both
# copies identical.
#
# --source-dir defaults to "." (wherever the script itself was invoked from)
# and is the anchor used to locate package.conf - it can never itself be
# sourced from that file. if <source-dir>/package.conf exists, it's sourced
# to set PKG_NAME/PKG_BUILD_CMD/PKG_DEST_DIR/PKG_SOURCE_DIR as defaults.
# --name/--dest-dir/--build-cmd/--source-dir all resolve as: CLI flag if
# given, else the matching PKG_* conf value, else (for --name/--dest-dir
# only) a hard error - package.conf supplies convenience defaults, it
# doesn't remove the requiredness checks. PKG_SOURCE_DIR lets a conf file
# point at a source subdirectory different from where the conf file itself
# lives; unused by either current package, whose conf file already lives at
# its own source root.
#
# --build-cmd, if given, runs via eval from wherever the script itself was
# invoked (not --source-dir) immediately before the rsync staging step -
# only ever supplied by the trusted local operator on their own command
# line, never untrusted input. set -eu already aborts the publish on any
# nonzero exit from it.
#
# rsync excludes: '.git' is always excluded, hardcoded, regardless of
# .pkgignore. if <source-dir>/.pkgignore exists, its gitignore-syntax rules
# (via rsync's --exclude-from=) decide everything else left out of the
# tarball; if it doesn't, a small hardcoded safety net (vendor,
# node_modules, composer.lock) applies instead.
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
#
# versioning: an explicit --version always wins outright and skips every-
# thing below. otherwise, --channel stable bumps $source_dir/extension.json's
# "version" field, commits it, and tags it (${pkg_name}-<version>[.0]) before
# any build/sign work - mirrors doom-bot-dev/scripts/deploy.py's
# compute_new_version(): --bump minor (default) advances to the current attu
# year (reset to "<year>.0") or increments the minor component within the
# same year; --bump patch increments off the highest existing
# "${pkg_name}-<major>.<minor>.*" git tag. the attu year comes from
# ~/.attu-epoch.toml (the same [epoch] block doom-bot-dev's /fix epoch
# command produces, kept as a single host-level file instead of a third
# hardcoded copy - see doom-bot-dev's notes/bugs.md "deploy" section) - if
# that file is missing, a --bump minor publish aborts rather than falling
# back to anything else. --channel latest never bumps/commits/tags
# (unaffected by any of this - a dev-build channel needs to stay usable
# while iterating on uncommitted local changes, same reasoning as the
# clean-tree gate below), and keeps resolving from extension.json's current
# (unbumped) version, then a UTC timestamp, same as before this existed.
#
# regardless of channel, the permanent per-version tarball name gets a
# short content-hash suffix (${pkg_name}-<version>-<hash>.tar.zst) as a
# second, independent safety net against silently overwriting a prior
# release's archived tarball - even a forgotten bump or a manually-collided
# --version can't clobber history this way, since the hash still disambig-
# uates as long as anything actually shipped differs. this is a hash of the
# staged tree (post-.pkgignore, pre-tar), not "git rev-parse --short HEAD" -
# a commit sha would be wrong for --channel latest, whose whole point is
# tolerating a dirty working tree: two dirty-tree latest publishes at the
# same HEAD but different uncommitted content would collide on the same
# commit sha yet ship different bytes, silently overwriting each other -
# the exact failure mode this suffix exists to prevent. for --channel
# stable this is equivalent to a commit hash anyway (the clean-tree gate +
# auto-commit guarantee the staged tree always matches HEAD there), and the
# git tag already created gives full commit traceability for that case. the
# swung channel symlink (${pkg_name}-${channel}.tar.zst) is deliberately
# NOT suffixed - the Dockerfile fetch depends on that exact fixed name.
set -eu
setopt pipefail

# --- Parse args --------------------------------------------------------

typeset -a SOURCE_DIR_OPT NAME_OPT DEST_DIR_OPT VERSION_OPT SECRET_KEY_OPT CHANNEL_OPT BUILD_CMD_OPT BUMP_OPT HELP_FLAG
zparseopts -D -E -F -- \
    -source-dir:=SOURCE_DIR_OPT \
    -name:=NAME_OPT \
    -dest-dir:=DEST_DIR_OPT \
    -version:=VERSION_OPT \
    -secret-key:=SECRET_KEY_OPT \
    -channel:=CHANNEL_OPT \
    -build-cmd:=BUILD_CMD_OPT \
    -bump:=BUMP_OPT \
    -help=HELP_FLAG \
    h=HELP_FLAG

if (( ${#HELP_FLAG} )); then
    print -- "usage: zsh scripts/publish-package.zsh [--source-dir <dir>] [--name <name>]"
    print -- "           [--dest-dir <dir>] [--version <string>] [--secret-key <path>]"
    print -- "           [--channel {stable,latest}] [--build-cmd <command>]"
    print -- "           [--bump {minor,patch}]"
    print --
    print -- "builds a shipping-clean tarball from a mediawiki extension source tree,"
    print -- "signs it with minisign, then atomically publishes it under <dest-dir>/<name>/."
    print -- "reads <source-dir>/package.conf (default source-dir: .) for PKG_NAME/"
    print -- "PKG_BUILD_CMD/PKG_DEST_DIR/PKG_SOURCE_DIR defaults; CLI flags override."
    print -- "--channel stable bumps/commits/tags extension.json's version (--bump"
    print -- "minor, the default, needs ~/.attu-epoch.toml; --bump patch does not)."
    print -- "see this file's header comment for the full option-resolution rules."
    exit 0
fi

# --- Resolve source_dir anchor, then package.conf, then everything else ---
# anchor_dir is the directory that locates package.conf: --source-dir if
# given, else "." (wherever the script was invoked from). this one value
# genuinely can't come from package.conf itself.
if (( ${#SOURCE_DIR_OPT} )); then
    anchor_dir="${SOURCE_DIR_OPT[2]:A}"
else
    anchor_dir="${PWD:A}"
fi
[[ -d "$anchor_dir" ]] || { print -u2 -- "source dir not found: $anchor_dir"; exit 2; }

PKG_NAME="" PKG_BUILD_CMD="" PKG_DEST_DIR="" PKG_SOURCE_DIR=""
if [[ -f "$anchor_dir/package.conf" ]]; then
    source "$anchor_dir/package.conf"
fi

# effective source_dir (used for the clean-tree gate, version detection,
# and rsync staging): --source-dir flag if given (same as anchor_dir in
# that case), else PKG_SOURCE_DIR from the conf file, else the anchor
if (( ${#SOURCE_DIR_OPT} )); then
    source_dir="$anchor_dir"
elif [[ -n "$PKG_SOURCE_DIR" ]]; then
    source_dir="${PKG_SOURCE_DIR:A}"
else
    source_dir="$anchor_dir"
fi
[[ -d "$source_dir" ]] || { print -u2 -- "source dir not found: $source_dir"; exit 2; }

if (( ${#NAME_OPT} )); then
    pkg_name="${NAME_OPT[2]}"
elif [[ -n "$PKG_NAME" ]]; then
    pkg_name="$PKG_NAME"
else
    print -u2 -- "missing --name (or PKG_NAME in $anchor_dir/package.conf)"
    exit 2
fi

if (( ${#DEST_DIR_OPT} )); then
    dest_dir="${DEST_DIR_OPT[2]:A}"
elif [[ -n "$PKG_DEST_DIR" ]]; then
    dest_dir="${PKG_DEST_DIR:A}"
else
    print -u2 -- "missing --dest-dir (or PKG_DEST_DIR in $anchor_dir/package.conf)"
    exit 2
fi

if (( ${#BUILD_CMD_OPT} )); then
    build_cmd="${BUILD_CMD_OPT[2]}"
else
    build_cmd="${PKG_BUILD_CMD:-}"
fi

secret_key="${SECRET_KEY_OPT[2]:-$HOME/.minisign/minisign.key}"
channel="${CHANNEL_OPT[2]:-latest}"

case "$channel" in
    stable|latest) ;;
    *)
        print -u2 -- "invalid --channel: $channel (must be 'stable' or 'latest')"
        exit 2
        ;;
esac

bump="${BUMP_OPT[2]:-minor}"
case "$bump" in
    minor|patch) ;;
    *)
        print -u2 -- "invalid --bump: $bump (must be 'minor' or 'patch')"
        exit 2
        ;;
esac

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
    dirty_lines=("${(@f)$(git -C "$source_dir" status --porcelain -- .)}")
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

# --- Version resolution --------------------------------------------------
# current_ver is read regardless of channel - it's the bump baseline for
# stable and the existing fallback for latest (or an explicit --version on
# either channel, which always wins outright - see header comment).
current_ver=""
if [[ -f "$source_dir/extension.json" ]]; then
    current_ver="$(python3 -c "
import json
print(json.load(open('$source_dir/extension.json')).get('version', ''))
")"
fi

if [[ "$channel" == "stable" ]] && (( ! ${#VERSION_OPT} )); then
    [[ -n "$current_ver" ]] || { print -u2 -- "abort: $source_dir/extension.json has no version to bump from"; exit 1; }
    tag_prefix="${pkg_name}-"
    current_parts=("${(@s:.:)current_ver}")

    if [[ "$bump" == "minor" ]]; then
        epoch_file="$HOME/.attu-epoch.toml"
        if [[ ! -f "$epoch_file" ]]; then
            print -u2 -- "abort: $epoch_file not found - required to compute the current attu year for a minor bump"
            print -u2 -- "  copy the [epoch] block doom-bot-dev's /fix epoch command last produced into that file"
            print -u2 -- "  (or publish with --bump patch, which doesn't need it)"
            exit 1
        fi
        attu_year="$(python3 - "$epoch_file" <<'PY'
import sys
import tomllib
from datetime import date, datetime
from zoneinfo import ZoneInfo

with open(sys.argv[1], 'rb') as f:
    epoch = tomllib.load(f)['epoch']

tz = ZoneInfo('UTC')
rollover_minutes = epoch['rollover_minutes']
rollover_time = datetime.min.time().replace(hour=rollover_minutes // 60, minute=rollover_minutes % 60, tzinfo=tz)
epoch_dt = datetime.combine(datetime.fromtimestamp(epoch['time']).astimezone(), rollover_time)
today_dt = datetime.combine(date.today(), rollover_time)
elapsed_days = int((today_dt - epoch_dt).total_seconds() / 86400)
year = epoch['year'] + (elapsed_days // epoch['length'])
if (elapsed_days % epoch['length']) == 0 and datetime.now().astimezone() < today_dt:
    year -= 1
print(year)
PY
)"
        if (( current_parts[1] != attu_year )); then
            version="${attu_year}.0"
        else
            version="${current_parts[1]}.$(( current_parts[2] + 1 ))"
        fi
        new_tag="${tag_prefix}${version}.0"
    else
        # patch - find the highest existing tag for the current major.minor, +1
        base="${current_parts[1]}.${current_parts[2]}"
        max_patch=-1
        for tag in "${(@f)$(git -C "$source_dir" tag -l "${tag_prefix}${base}.*")}"; do
            [[ -z "$tag" ]] && continue
            tag_ver="${tag#$tag_prefix}"
            tag_parts=("${(@s:.:)tag_ver}")
            (( ${#tag_parts} == 3 )) || continue
            [[ "${tag_parts[3]}" =~ '^[0-9]+$' ]] || continue
            [[ "${tag_parts[1]}.${tag_parts[2]}" == "$base" ]] || continue
            (( tag_parts[3] > max_patch )) && max_patch="${tag_parts[3]}"
        done
        version="${base}.$(( max_patch + 1 ))"
        new_tag="${tag_prefix}${version}"
    fi

    print -P "%F{cyan}[publish]%f bumping version: $current_ver -> $version (tag: $new_tag)"
    python3 -c "
import re
path = '$source_dir/extension.json'
content = open(path).read()
updated = re.sub(r'\"version\"\s*:\s*\"[^\"]*\"', '\"version\": \"$version\"', content, count=1)
open(path, 'w').write(updated)
"
    git -C "$source_dir" add -- extension.json
    git -C "$source_dir" commit --quiet -m "chore(publish): bump version to ${version}"
    if ! git -C "$source_dir" tag "$new_tag"; then
        git -C "$source_dir" reset --hard --quiet HEAD~1
        print -u2 -- "abort: tag $new_tag already exists - bump commit rolled back, resolve manually"
        exit 1
    fi
elif (( ${#VERSION_OPT} )); then
    version="${VERSION_OPT[2]}"
else
    version="${current_ver:-$(date -u +%Y%m%dT%H%M%SZ)}"
fi

pkg_dir="$dest_dir/$pkg_name"
mkdir -p "$pkg_dir"

print -P "%F{cyan}[publish]%f $pkg_name $version <- $source_dir"

# --- Optional build step ------------------------------------------------
# runs from the invoking shell's cwd, not $source_dir - e.g.
# `pnpm -F wiki-editor build` needs pnpm workspace context, not the
# extension subdirectory. runs after the clean-tree gate above and
# immediately before the rsync staging step below, so freshly-built
# output is always what gets staged.
if [[ -n "$build_cmd" ]]; then
    print -P "%F{cyan}[publish]%f running build command: $build_cmd"
    eval "$build_cmd"
fi

# --- Build: stage a shipping-clean copy of the source tree -------------
# .git is always excluded, hardcoded, regardless of .pkgignore presence.
# if $source_dir/.pkgignore exists, its gitignore-syntax rules (via
# rsync's --exclude-from=) decide everything else the tarball leaves out;
# if it doesn't, fall back to a minimal hardcoded safety net so a source
# dir without a .pkgignore yet still doesn't ship git internals or
# dependency trees.
build_dir="$(mktemp -d)"
trap 'rm -rf "$build_dir"' EXIT

stage_dir="$build_dir/$pkg_name"
typeset -a rsync_excludes
rsync_excludes=(--exclude='.git')
if [[ -f "$source_dir/.pkgignore" ]]; then
    rsync_excludes+=(--exclude-from="$source_dir/.pkgignore")
else
    rsync_excludes+=(--exclude='vendor' --exclude='node_modules' --exclude='composer.lock')
fi
rsync --archive "${rsync_excludes[@]}" "$source_dir/" "$stage_dir/"

# short content-hash suffix for the permanent tarball name - see header
# comment for why this hashes the staged tree rather than the git commit
content_hash="$(find "$stage_dir" -type f -print0 | sort -z | xargs -0 sha256sum | sha256sum | cut -c1-8)"

# --- Tar + sign into place-adjacent tmp files (same filesystem as the ---
# --- final destination, so the later move is a true atomic rename)    ---
tar_name="${pkg_name}-${version}-${content_hash}.tar.zst"
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
