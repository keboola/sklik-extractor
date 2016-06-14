#!/bin/bash

docker login -e="." -u="$QUAY_USERNAME" -p="$QUAY_PASSWORD" quay.io
docker tag keboola/sklik-extractor quay.io/keboola/sklik-extractor:$TRAVIS_TAG
docker images
docker push quay.io/keboola/sklik-extractor:$TRAVIS_TAG