```
    "extra": {
        "evolaze-binary-symlink": {
            "filemode" => "0411",
            "links" => {
                "subdir0",
                {
                    "from": "subdir1",
                    "filemode": "0511"
                },
                {
                    "from": "path/to/some_bin",
                    "to": "bin"
                },
                {
                    "from": "path/to/some_bin",
                    "to": "bin",
                    "filemode": "0755"
                }
            }
        }
    }

```

* create the very single symlink from `app/path/to/some_bin` to `bin/some_bin`
* change filemode for symlink source - `app/path/to/some_bin`
