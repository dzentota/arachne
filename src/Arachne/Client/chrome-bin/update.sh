#!/usr/bin/env bash
# Author: Alex Tatulchenkov <webbota@gmail.com>
# Download ChromeDriver binaries and Chrome browser

VERSION=

Help()
{
   # Display Help
   echo
   echo "Download ChromeDriver binaries and Chrome browser"
   echo
   echo "Syntax: update.sh [-v|h]"
   echo "options:"
   echo "v     Version of ChromeDriver to download. It should correspond to your Chrome version. Latest version by default"
   echo "h     Print this Help."
   echo
   echo "e.g. update.sh -v 87.0.4280.88"
   echo
}

while getopts v:h: flag
do
    case "${flag}" in
        v) VERSION=${OPTARG};;
        h) Help
           exit;;
        \?) echo "Invalid option"
            Help
            exit;;
    esac
done

cd "$(dirname "$0")"
apt-get update && apt-get install -y unzip
echo "Downloading Chrome..."
curl https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb -o /tmp/chrome.deb
dpkg -i /tmp/chrome.deb || apt-get install -yf
rm /tmp/chrome.deb

if [[ -z "$VERSION" ]]; then
VERSION=$(curl -s https://chromedriver.storage.googleapis.com/LATEST_RELEASE)
fi
echo "Downloading ChromeDriver version ${VERSION}..."
curl -s https://chromedriver.storage.googleapis.com/${VERSION}/chromedriver_linux64.zip -Oc
unzip -q -o chromedriver_linux64.zip
rm chromedriver_linux64.zip
if [[ -f "chromedriver" ]]; then
  mv chromedriver /usr/local/bin/chromedriver
  chmod +x /usr/local/bin/chromedriver
fi
curl -s https://chromedriver.storage.googleapis.com/${VERSION}/notes.txt -O
echo "Done."