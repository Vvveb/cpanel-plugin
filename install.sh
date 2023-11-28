#!/bin/bash
mkdir -p /usr/local/cpanel/base/frontend/paper_lantern/vvveb

cp vvveb.tar /usr/local/cpanel/base/frontend/paper_lantern/vvveb
cp vvveb.live.php /usr/local/cpanel/base/frontend/paper_lantern/vvveb/vvveb.live.php

/usr/local/cpanel/scripts/install_plugin /usr/local/cpanel/base/frontend/paper_lantern/vvveb/vvveb.tar --theme paper_lantern

mkdir -p /usr/local/cpanel/base/frontend/jupiter/vvveb

cp vvveb.tar /usr/local/cpanel/base/frontend/jupiter/vvveb/vvveb.tar
cp vvveb.live.php /usr/local/cpanel/base/frontend/jupiter/vvveb/vvveb.live.php

/usr/local/cpanel/scripts/install_plugin /usr/local/cpanel/base/frontend/jupiter/vvveb/vvveb.tar --theme jupiter
