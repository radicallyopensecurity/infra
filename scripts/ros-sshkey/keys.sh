#!/bin/bash
#
# really dumb ssh pubkey management script.
# 20140112 -- peter@haxx.in

KEYFILE=keys

# no arguments? dump all key identifiers
if [ $# -eq 0 ]; then
	echo "## Key identifiers from '$KEYFILE':"

	for i in `cat "$KEYFILE" | awk '{ print $3 }'`; do
		echo "> $i"
	done
else
	for id in "$@"; do
		RES=`awk '$3 == "'$id'"' $KEYFILE`
		LNC=`echo "$RES" | wc -l`

		if [ "$RES" == "" ]; then
			echo "** WARNING: key with identifier '$id' NOT found."
		elif [ "$LNC" == "0" ]; then
			echo "** WARNING: key with identifier '$id' NOT found."
		elif [ "$LNC" != "1" ]; then
			echo "** WARNING: multiple keys found with identifier '$id'."
			echo "$RES"
		else
			echo "$RES"
		fi
	done
fi
