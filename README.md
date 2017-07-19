# Binary distribution script handler #

Replace platform dependent symlink commands like

```
    "ln -sf ../app/bin/some-binary.sj bin/some-binary.sh"
    "ln -sf ../app/bin/some-other-binary.sj bin/other-binary.sh"
```

with extra config section and post install or\and update trigger

## Installation ##

 
```
composer require evolaze/binary-symlink
```

In order to add link from **app/from.sh** to **bin/to.sh**

### Composer.json ###

```
    "scripts": {
        "post-install-cmd": [
            "Evolaze\\BinarySymlink\\ScriptHandler::installBinary"
        ]
    },
    "extra": {
        "evolaze-binary-symlink": {
            "links": [
                {
                    "from": "from",
                    "to": "to"
                }
            ]
        }
    }
```

#### Defaults ####

* Default dir to create links from is **app**
* Default dir to create links to is **bin**

See other [examples](resources/docs) and [tests](tests/src/BinarySymlinkTest.php)

## Tests ##


`./bin/phpunit -c ./tests/phpunit.xml` - php 7.1+ must be installed
 
or
 
`./app/composer.sh run-script test`  - docker must be installed
