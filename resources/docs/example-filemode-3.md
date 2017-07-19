```
    "extra": {
        "evolaze-binary-symlink": {
            "links" => {
                {
                    "from": "path/to/some_bin",
                    "to": "bin",
                    "filemode" => "0755"
                }
            }
        }
    }

```

* create the very single symlink from `app/path/to/some_bin` to `bin/some_bin`
* change filemode for symlink source - `app/path/to/some_bin`
