<?php

/*
Plugin Name: Unused Functions Tracker
Description: Tracks custom functions in a theme that are not hooked into any WordPress hooks and lists the files they are in.
Version: 1.0
Author: Tillal Saeed
*/

add_action('admin_menu', 'uft_add_admin_menu');
add_action('admin_init', 'uft_run_scan');

function uft_add_admin_menu()
{
    add_menu_page('Unused Functions Tracker', 'Unused Functions Tracker', 'manage_options', 'unused-functions-tracker', 'uft_admin_page');
}

function uft_run_scan()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $functions = uft_scan_theme_functions();
    update_option('uft_scanned_functions', $functions);
}

function uft_scan_theme_functions()
{
    $directory = new RecursiveDirectoryIterator(get_template_directory());
    $iterator = new RecursiveIteratorIterator($directory);
    $regex = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

    $functions = [];
    foreach ($regex as $file) {
        $content = file_get_contents($file[0]);
        if (preg_match_all('/function\s+(\w+)/', $content, $matches)) {
            foreach ($matches[1] as $function) {
                $functions[$function] = $file[0];
            }
        }
    }

    // Remove functions that are hooked
    foreach ($functions as $function => $file) {
        if (uft_is_function_hooked($function)) {
            unset($functions[$function]);
        }
    }

    return $functions;
}

function uft_is_function_hooked($function_name)
{
    global $wp_filter;

    foreach ($wp_filter as $hooks) {
        foreach ($hooks as $priority) {
            foreach ($priority as $hook) {
                if (is_array($hook['function'])) {
                    if ($hook['function'][1] === $function_name) {
                        return true;
                    }
                } elseif (is_string($hook['function']) && $hook['function'] === $function_name) {
                    return true;
                }
            }
        }
    }

    return false;
}

function uft_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $functions = get_option('uft_scanned_functions', []);
?>
    <div class="wrap">
        <h1>Unused Functions Tracker</h1>
        <table class="widefat fixed">
            <thead>
                <tr>
                    <th>Function Name</th>
                    <th>File</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($functions as $function => $file) : ?>
                    <tr>
                        <td><?php echo esc_html($function); ?></td>
                        <td><?php echo esc_html($file); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php
}
