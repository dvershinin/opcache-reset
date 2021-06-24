<?php

// check if opcache is installed and display notice with link to installing (based on OS!)
// check for return of opcache_get_status() in wp-admin pages. if revalidating PHP files is enabled, show admin notice with link to configuration instructions

// var_dump(PHP_OS);

// hook into all things same to here for invalidation: https://plugins.svn.wordpress.org/advanced-code-editor/trunk/advanced-code-editor.php
/*
echo php_uname(); // use different mode parameter for later
echo '<br>';
echo php_uname('s');
*/

function getLinuxDistro()
{
    //declare Linux distros(extensible list).
    $distros = array(
        "Arch" => "arch-release",
        "Debian" => "debian_version",
        "Fedora" => "fedora-release",
        "Ubuntu" => "lsb-release",
        'Redhat' => 'redhat-release',
        'CentOS' => 'centos-release');
    //Get everything from /etc directory.
    $etcList = scandir('/etc');

    //Loop through /etc results...
    $OSDistro =  null;
    foreach ($etcList as $entry)
    {
        //Loop through list of distros..
        foreach ($distros as $distroReleaseFile)
        {
            //Match was found.
            if ($distroReleaseFile === $entry)
            {
                //Find distros array key(i.e. Distro name) by value(i.e. distro release file)
                $OSDistro = array_search($distroReleaseFile, $distros);

                break 2;//Break inner and outer loop.
            }
        }
    }

    return $OSDistro;

}

// echo getLinuxDistro();

function opcache_edit_success() {
    ?>
    <style>.updated.notice { display: none; }</style>
    <div class="notice notice-success is-dismissible">
        <p><?php _e( 'File edited successfully and invalidated in bytecode cache.', 'sample-text-domain' ); ?></p>
    </div>
    <?php
}
function opcache_edit_warning() {
    ?>
    <div class="notice notice-warning is-dismissible">
        <p><?php _e( 'Opcache had nothing to invalidate for ' . WP_PLUGIN_DIR . '/' . $_REQUEST['file'] . '.', 'sample-text-domain' ); ?></p>
    </div>
    <?php
}

// Hook into plugin editor
// TODO invalidate file cache (WordPress core does not)
add_action('load-plugin-editor.php', 'opcache_plugin_editor');
function opcache_plugin_editor() {
    if (isset($_GET['a']) && isset($_REQUEST['file'])) {
       // File was edited successfully and we know the filename
       if (opcache_invalidate(WP_PLUGIN_DIR . '/' . $_REQUEST['file'])) {
          add_action( 'admin_notices', 'opcache_edit_success', 999 );
       } else {
          add_action( 'admin_notices', 'opcache_edit_warning', 999 );
       }
    }
}

// TODO invalidate file cache (WordPress core does not)
function opcache_admin_init() {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && (current_user_can('edit_themes') || current_user_can('edit_plugins'))) {
            if (isset($_REQUEST['file'])) {
                        opcache_invalidate($_REQUEST['file']);
            } elseif (isset($_REQUEST['action']) && isset($_REQUEST['filename'])) {
                opcache_invalidate($_REQUEST['filename']);
            }
  }
}
add_action( 'init', 'opcache_admin_init', 1 );