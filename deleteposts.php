<?php
/*
Plugin Name: Bulk Post Delete
Description: This plugin deletes all published posts within a certain period of time. And, if selected, also the corresponding post images. 
Version: 1.0
Author: <a href="https://www.nerd-bert.com" target="_blank">Nerd Bert</a>
*/

// Plugin-Aktivierung und Deaktivierung
register_activation_hook(__FILE__, 'custom_post_cleaner_activate');
register_deactivation_hook(__FILE__, 'custom_post_cleaner_deactivate');

function custom_post_cleaner_activate() {
    // Fügen Sie hier Aktivierungsroutinen hinzu, falls erforderlich.
}

function custom_post_cleaner_deactivate() {
    // Fügen Sie hier Deaktivierungsroutinen hinzu, falls erforderlich.
}

// Admin-Seite hinzufügen
add_action('admin_menu', 'custom_post_cleaner_menu');

function custom_post_cleaner_menu() {
    add_menu_page('Bulk Post Delete', 'Bulk Post Delete', 'manage_options', 'post-cleaner', 'custom_post_cleaner_page');
}

function custom_post_cleaner_page() {
    // Überprüfen, ob der Benutzer die erforderlichen Berechtigungen hat
    if (!current_user_can('manage_options')) {
        return;
    }

    // Prüfen, ob das Formular gesendet wurde
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['custom_post_cleaner_submit'])) {
        // Verarbeitung der Formulardaten und Löschen der Artikel und Bilder
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $delete_images = isset($_POST['delete_images']) ? true : false;
        $selected_author = sanitize_text_field($_POST['selected_author']);
        custom_post_cleaner_delete_posts($start_date, $end_date, $delete_images, $selected_author);
    }

    // Holen Sie die Autorenliste
    $authors = get_users(array('role__in' => array('author', 'editor', 'bearbeiter', 'administrator', 'contributor')));
    $author_options = array('all' => 'All Authors');
    foreach ($authors as $author) {
        // Überprüfen, ob der Autor Artikel veröffentlicht hat
        $args = array(
            'author' => $author->ID,
            'post_type' => 'post',
            'posts_per_page' => 1,
            'post_status' => 'publish',
        );
        $published_posts = get_posts($args);
        if ($published_posts) {
            $author_options[$author->ID] = $author->display_name;
        }
    }

    // Formular zur Einstellung des Zeitraums anzeigen
    ?>
    <div class="wrap">
        <h1>Bulk Post Delete</h1>
        <h2>Bulk deletion of all posts within a certain time period</h2>
Be careful when using this plugin! It deletes all published posts in a period of time.<br>Select the date before the date of the posts to be deleted as the start date. The end date is the date after the date of the posts to be deleted.<br>So if you want to delete articles in the period from the 15th of October to the 20th of October, select the 14th as the start date and the 21st as the end date.<br>You can also delete the posts featured image linked to the post from the media library at the same time if the checkbox is activated.<br><br><b>ATTENTION: This step is not reversible! The posts are not transferred to the recycle bin but deleted completely. This also applies to the posts featured image, if the checkbox is selected!</b><br><br>Use at your own risk. Have fun, yours, <a href="https://www.nerd-bert.com" target = "_blank">Nerd Bert</a><br><br> BTW: There is no and will not be a PRO Version of this plugin. But if you like what I am doing, please <a href="https://www.paypal.com/donate/?hosted_button_id=6PRSHVD6GCG6Q" target="_blank">buy me a coffee (Paypal)</a><br><br><hr><br>

        <form method="post">
            <label for="start_date">Start date:</label><br>
            <input type="date" id="start_date" name="start_date" required>
            <br><br>
            <label for="end_date">End date: </label><br>
            <input type="date" id="end_date" name="end_date" required>
            <br><br>
            <label for="selected_author">From Author:</label><br>
            <select id="selected_author" name="selected_author">
                <?php foreach ($author_options as $key => $value) { ?>
                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                <?php } ?>
            </select>
            <br><br>
            <label for="delete_images">Also delete the featured image linked in posts from the media library.</label>
            <input type="checkbox" id="delete_images" name="delete_images">
            <br><br>
            <input type="submit" name="custom_post_cleaner_submit" value="Delete all posts">
        </form>
    </div>
    <?php
}

function custom_post_cleaner_delete_images($post_content) {
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML($post_content);
    libxml_use_internal_errors(false);

    $xpath = new DOMXPath($dom);
    $images = $xpath->query('//img/@src');

    foreach ($images as $image) {
        $image_src = $image->value;
        $upload_dir = wp_upload_dir();
        $image_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_src);

        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
}

function custom_post_cleaner_delete_posts($start_date, $end_date, $delete_images, $selected_author) {
    $args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'date_query' => array(
            'after' => $start_date,
            'before' => $end_date,
        ),
        'posts_per_page' => -1,
    );

    if ($selected_author != 'all') {
        $args['author'] = $selected_author;
    }

    $posts = get_posts($args);

    foreach ($posts as $post) {
        if ($delete_images) {
            // Lösche die Bilder im Artikel
            custom_post_cleaner_delete_images($post->post_content);

            // Lösche das Beitragsbild (Thumbnail)
            $thumbnail_id = get_post_thumbnail_id($post->ID);
            if ($thumbnail_id) {
                wp_delete_attachment($thumbnail_id, true);
            }
        }

        // Lösche den Artikel
        wp_delete_post($post->ID, true);
    }
}

