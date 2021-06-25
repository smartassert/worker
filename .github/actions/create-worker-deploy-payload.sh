#!/usr/bin/env bash

payload=""

while read line
do
#  echo "before loop echo"
#  echo $payload
#  echo "after loop echo"
  key=$(echo $line | cut -d'=' -f1)
  value=$(echo $line | cut -d'=' -f2)
#  echo $key
#  echo $value
  [ ! -z "$payload" ] && payload="$payload, "
  payload="$payload\"$key\": \"$value\""
done <<<$(cat .image-versions.env)

echo $payload
