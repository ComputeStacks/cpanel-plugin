# cPanel integration for ComputeStacks

The installation process is a 2 step procedure:

1) Create the ComputeStacks plugin directory (`mkdir /usr/local/cpanel/base/frontend/paper_lantern/computestacks`) and move the contents of `plugin_files` to: `/usr/local/cpanel/base/frontend/paper_lantern/computestacks`

2) Install the plugin with: `/usr/local/cpanel/scripts/install_plugin computestacks.tar.gz`

3) Login to WHM and add ComputeStacks in the feature manager

4) Thats it! It will be visible in the Software section within cPanel.

If you wish to uninstall ComputeStacks, you can do so by removing the plugin files and running: `/usr/local/cpanel/scripts/uninstall_plugin computestacks.tar.gz`

## Building

Compress with `tar --exclude='.DS_Store' -czvf computestacks.tar.gz computestacks/`.
