```
    "extra": {
        "evolaze-binary-symlink": {
            "links" => [
                "path/to/some_dir/from/app_dir",
                "path/to/other_dir/from/app_dir"
            ]
        }
    }

```

Create symlinks for every file founded in the `app/path/to/some_dir` and in the `app/path/to/other_dir`

For example: for `tests/from/subdir` and `tests/from/other-subdir` there will be 4 symlinks
