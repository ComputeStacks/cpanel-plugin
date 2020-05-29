# Building Plugin

1. Make local changes to `plugin_files/computestacks.ini`. (remove sample file)
2. remove `sync.sh`
3. Make any local changes to `computestacks/install.json`.
4. Compress plugin with: `tar --exclude='.DS_Store' -czvf computestacks.tar.gz computestacks/`
5. Remove the following files:
    1. `sync.sh`
    2. `beta.live.php`
    3. `computestacks.ini.sample` -- You should only have a single `computestacks.ini` file with the customer details.
    4. `BUILD.md`
    5. `computestacks` directory (after compressing in step 4)
5. Zip the entire outer directory
