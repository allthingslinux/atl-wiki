#!/usr/bin/env python3

#  Copyright 2025 atmois <atmois@allthingslinux.org>
#
#  Licensed under the Apache License, Version 2.0 (the "License");
#  you may not use this file except in compliance with the License.
#  You may obtain a copy of the License at
#      http://www.apache.org/licenses/LICENSE-2.0

import json
import os
import subprocess
import sys

MEDIAWIKI_BRANCH = os.environ.get('MEDIAWIKI_BRANCH')
EXTENSIONS_JSON = '/tmp/extensions.json'
EXTENSIONS_DIR = '/var/www/wiki/mediawiki/extensions'

def run(command):
    """
    Run a shell command and handle errors

    args:
        command (str): The shell command to run
    """
    print(f'Running: {command}')
    try:
        subprocess.check_call(command, shell=True)
    except subprocess.CalledProcessError as e:
        print(f'Command failed with exit code {e.returncode}: {command}', file=sys.stderr)
        raise

def main():
    """
    Install MediaWiki extensions as specified in the extensions.json file
    """

    with open(EXTENSIONS_JSON, encoding='utf-8') as extensions_file:
        extensions = json.load(extensions_file)
    os.makedirs(EXTENSIONS_DIR, exist_ok=True)
    os.chdir(EXTENSIONS_DIR)
    for extension in extensions:
        extension_name = extension['extension_name']
        extension_url = extension['extension_url']
        install_type = extension['install_type']
        git_branch = extension.get('git_branch', MEDIAWIKI_BRANCH)
        print(f"Installing {extension_name}...")

        if install_type == 'git':
            run(f"git clone \
                --branch {git_branch} --single-branch --depth 1 {extension_url} {extension_name}")
        elif install_type == 'tarball':
            tarball_name = f"{extension_name}.tar.gz"
            run(f"curl -fsSL {extension_url} -o {tarball_name}")
            run(f"mkdir -p {extension_name}")
            run(f"tar -xzf {tarball_name} -C {extension_name} --strip-components=1")
            run(f"rm {tarball_name}")
        else:
            print(f"Unknown install_type for {extension_name}: {install_type}", file=sys.stderr)
            sys.exit(1)

        if 'post_install_commands' in extension:
            print(f"Running post-install commands for {extension_name}...")
            original_dir = os.getcwd()
            try:
                os.chdir(extension_name)
                for command in extension['post_install_commands']:
                    run(command)
            finally:
                os.chdir(original_dir)

        print(f"✓ {extension_name} installed successfully")

if __name__ == '__main__':
    main()
