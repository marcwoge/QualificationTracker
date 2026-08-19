#!/bin/sh
#
# QualificationTracker – build a release archive (F8.7).
#
# Produces a clean, deployable plugin archive from a committed state using
# `git archive`. Development-only paths (docker/, tests/, phpunit.xml.dist, …)
# are excluded via the `export-ignore` attributes in .gitattributes, so the
# archive contains exactly what belongs on a MantisBT server.
#
# The archive unpacks to a top-level `QualificationTracker/` directory that can
# be dropped straight into `<mantis>/plugins/`.
#
# Usage:
#   scripts/qt_package.sh [ref]
#     ref   git ref to archive (tag, branch, commit); default HEAD
#
# Examples:
#   scripts/qt_package.sh            # archive the current HEAD
#   scripts/qt_package.sh v1.0.0     # archive the released tag
#
# @package   QualificationTracker
# @author    Marc-Philipp Woge <marc.woge@googlemail.com>
# @copyright Copyright (c) 2026 Marc-Philipp Woge
# @license   MIT

set -eu

# Move to the repository root (the directory above scripts/).
cd "$(dirname "$0")/.."

REF="${1:-HEAD}"

# Derive the version from the plugin manifest so the filename always matches.
VERSION="$(sed -n "s/.*\$this->version[[:space:]]*=[[:space:]]*'\([^']*\)'.*/\1/p" QualificationTracker.php)"
if [ -z "$VERSION" ]; then
	echo "qt_package: could not read version from QualificationTracker.php" >&2
	exit 1
fi

OUT="QualificationTracker-${VERSION}.tar.gz"

git archive --format=tar.gz --prefix=QualificationTracker/ -o "$OUT" "$REF"

echo "Built $OUT from $REF"
echo "Contents:"
tar -tzf "$OUT" | sed 's/^/  /' | head -40
COUNT="$(tar -tzf "$OUT" | wc -l | tr -d ' ')"
echo "  ... ($COUNT entries total)"
