#!/usr/bin/env bash

FOO=$(ls tests/Image --ignore=Abstract*)
BAR="${FOO//.php/}"

echo "$BAR"

