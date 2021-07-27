#!/usr/bin/env bash
while read -r tag; do
    git tag -f $tag
done < <(git tag -l)
