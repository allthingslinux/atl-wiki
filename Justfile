set working-directory := justfile_directory()

import 'just/docker.just'
import 'just/base.just'
import 'just/extra.just'
import 'just/init.just'
import 'just/help.just'
import 'just/opensearch.just'

# Show available recipes
default:
    just help
