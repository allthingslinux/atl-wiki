#!/usr/bin/env python3
import json
import os
import subprocess
import sys

MEDIAWIKI_BRANCH = os.environ.get('MEDIAWIKI_BRANCH')
EXTENSIONS_JSON = '/tmp/extensions.json'
EXTENSIONS_DIR = '/var/www/atlwiki/mediawiki/extensions'

def run(cmd):
    print(f'Running: {cmd}')
    subprocess.check_call(cmd, shell=True)

def main():
    with open(EXTENSIONS_JSON) as extensions_file:
        extensions = json.load(extensions_file)
    os.makedirs(EXTENSIONS_DIR, exist_ok=True)
    os.chdir(EXTENSIONS_DIR)
    for extension in extensions:
        extension_name = extension['extension_name']
        extension_url = extension['extension_url']
        install_type = extension['install_type']
        git_branch = extension.get('git_branch', MEDIAWIKI_BRANCH)
        if install_type == 'git':
            run(f"git clone --branch {git_branch} --single-branch --depth 1 {extension_url} {extension_name}")
        elif install_type == 'tarball':
            tarball_name = f"{extension_name}.tar.gz"
            run(f"curl -L {extension_url} -o {tarball_name}")
            run(f"mkdir -p {extension_name}")
            run(f"tar -xzf {tarball_name} -C {extension_name} --strip-components=1")
            run(f"rm {tarball_name}")
        else:
            print(f"Unknown install_type for {extension_name}: {install_type}", file=sys.stderr)
            sys.exit(1)

if __name__ == '__main__':
    main()
