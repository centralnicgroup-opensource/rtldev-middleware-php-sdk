#!/bin/bash

FILES=$(find . -not \( -path ./vendor -prune \) -type f \( -iname \*.php -o -iname \*.phtml \))
for file in $FILES
do
    /usr/bin/php -l "${file:2}" 1> /dev/null
    retVal=$?
    if [ $retVal -ne 0 ]
    then 
        exit 42;
    fi
done