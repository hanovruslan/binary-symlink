```
    "extra": {
        "evolaze-binary-symlink": {
            "filemode" => "0755",
            "links" => {
                "path/to/some_dir/from/app_dir",
                {
                    "from": "path/to/some_bin/from/app_dir",
                    "to": "bin",
                    "filemode" => "0700", 
                }
            }
        }
    }

```

* create symlinks for every file founded in the `app/path/to/some_dir`
* create symlink from `app/path/to/some_bin` to `bin/bin`
* change filemode (`0755`) for symlink source in the app/path/to/some_dir
* change filemode (`0700`) for `app/path/to/some_bin`
