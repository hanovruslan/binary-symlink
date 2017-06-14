# Binary distribution script handler #

Replace platform dependent symlink commands like

```
    "ln -sf ../app/bin/some-binary.sj bin/some-binary.sh"
    "ln -sf ../app/bin/some-other-binary.sj bin/other-binary.sh"
```

with extra config section and post install or\and update trigger

## Installation ##

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

**links** section can be reduced down to

```
            "links": [
                "from_1",
                "from_2"
            ]

```

if there is no requirement for link name

Source and target dir for links configured with

```
    "extra": {
        "evolaze-binary-symlink": {
            "from-dir": "app",
            "to-dir": "bin",
        }
    }
```

Also specify only dir in links and ScriptHandler will scan it and will create links for all of the files in this dir

```
    "extra": {
        "evolaze-binary-symlink": {
            "links": [
                "subdir"
            ]
        }
    }

```

```
composer require evolaze/binary-symlink:^2.0.0
```

## Tests ##

```
./bin/phpunit -c ./tests/phpunit.xml
```
