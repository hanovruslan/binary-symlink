```
    "extra": {
        "evolaze-binary-symlink": {
            "filemode" => "0755",
            "links" => "path/to/some_bin/from/app_dir"
        }
    }

```

* create the very single symlink from `app/path/to/some_bin` to `bin/some_bin`
* change filemode for symlink source - `app/path/to/some_bin`
