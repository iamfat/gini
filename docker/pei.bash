#!/bin/bash

for ext in "$@"; do
    if [ -f /usr/local/share/pei/$ext.bash ]; then
        source /usr/local/share/pei/$ext.bash
    else
        apk add php7-$ext
    fi
done
