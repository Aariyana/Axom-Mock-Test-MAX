<?php
/*
Plugin Name: Axom Mock Test MAX (Pro)
Description: Advanced Exam Platform with Bulk Import & Leaderboard.
Version: 7.0
Author: Axom Xarothi
*/

if (!defined('ABSPATH')) exit;

// ================= DATABASE SETUP =================
register_activation_hook(__FILE__, 'axom_db_setup');
function axom_db_setup() {
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $charset = $wpdb->get_charset_collate();

    $q_table = $wpdb->prefix . 'axom_questions';
    $l_table = $wpdb->prefix . 'axom_leaderboard';

    $sql1 = "CREATE TABLE $q_table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question TEXT NOT NULL,
        option_a TEXT, option_b TEXT, option_c TEXT, option_d TEXT,
        correct CHAR(1),
        category VARCHAR(50)
    ) $charset;";

    $sql2 = "CREATE TABLE $l_table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        score FLOAT,
        correct INT,
        wrong INT,
        created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset;";

    dbDelta($sql1);
    dbDelta($sql2);
}

// ================= ADMIN DASHBOARD =================
add_action('admin_menu', function() {
    add_menu_page('Axom Exam', 'Axom Exam', 'manage_options', 'axom-exam', 'axom_admin_page', 'dashicons-welcome-learn-more');
});

function axom_admin_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'axom_questions';

    // Bulk Import Logic
    if (isset($_POST['bulk_import'])) {
        $data = explode("\n", trim($_POST['bulk_data']));
        foreach ($data as $line) {
            $row = str_getcsv($line);
            if (count($row) == 7) {
                $wpdb->insert($table, [
                    'question' => $row[0], 'option_a' => $row[1], 'option_b' => $row[2],
                    'option_c' => $row[3], 'option_d' => $row[4], 'correct' => strtolower($row[5]), 'category' => $row[6]
                ]);
            }
        }
        echo "<div class='updated'><p>✅ Bulk Questions Imported!</p></div>";
    }

    echo '<div class="wrap"><h1>Axom Mock Test Admin</h1>';
    echo '<div style="background:#fff; padding:20px; border:1px solid #ccc; border-radius:10px;">
    <h3>Bulk Import (CSV Format)</h3>
    <p>Format: Question,A,B,C,D,Correct(a/b/c/d),Category</p>
    <form method="post"><textarea name="bulk_data" style="width:100%; height:150px;" placeholder="Example: What is the capital of Assam?,Dispur,Guwahati,Tezpur,Silchar,a,GK"></textarea><br><br>
    <input type="submit" name="bulk_import" class="button button-primary" value="Import Questions"></form></div></div>';
}

// ================= FRONTEND SHORTCODE =================
add_shortcode('axom_mock_test', function() {
    global $wpdb;
    $qs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}axom_questions ORDER BY RAND() LIMIT 50");
    if (!$qs) return "No questions found.";

    ob_start(); ?>
    <style>
        .axom-box { max-width: 800px; margin: auto; background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); border-top: 10px solid #002D62; font-family: sans-serif; }
        .q-item { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 8px; border-left: 5px solid #002D62; }
        .timer-bar { position: sticky; top: 0; background: #002D62; color: #fff; padding: 10px; text-align: center; border-radius: 5px; font-weight: bold; z-index: 9; }
        input[type="radio"] { margin-right: 10px; }
        label { cursor: pointer; display: block; padding: 8px; border-radius: 4px; }
        label:hover { background: #e9ecef; }
        .btn-submit { background: #d9534f; color: white; border: none; padding: 15px 30px; width: 100%; border-radius: 5px; font-size: 18px; cursor: pointer; }
    </style>

    <div class="axom-box" id="axom_quiz_area">
        <div class="timer-bar" id="axom_timer">Time Left: 30:00</div>
        <input type="text" id="p_name" placeholder="Enter Full Name" style="width:100%; padding:10px; margin:20px 0;">
        <form id="axom_form">
            <?php foreach ($qs as $k => $q): ?>
                <div class="q-item">
                    <p><b>Q<?php echo $k+1; ?>. <?php echo esc_html($q->question); ?></b></p>
                    <label><input type="radio" name="q<?php echo $q->id; ?>" value="a"> <?php echo esc_html($q->option_a); ?></label>
                    <label><input type="radio" name="q<?php echo $q->id; ?>" value="b"> <?php echo esc_html($q->option_b); ?></label>
                    <label><input type="radio" name="q<?php echo $q->id; ?>" value="c"> <?php echo esc_html($q->option_c); ?></label>
                    <label><input type="radio" name="q<?php echo $q->id; ?>" value="d"> <?php echo esc_html($q->option_d); ?></label>
                </div>
            <?php endforeach; ?>
            <button type="button" class="btn-submit" onclick="axomSubmit()">Submit Exam</button>
        </form>
    </div>

    <div id="axom_result" class="axom-box" style="display:none; text-align:center;">
        <h2>Exam Result</h2>
        <div id="final_score" style="font-size:30px; margin:20px;"></div>
        <button onclick="location.reload()" style="padding:10px;">Try Again</button>
    </div>

    <script>
        let timeLeft = 1800;
        let timer = setInterval(() => {
            timeLeft--;
            let m = Math.floor(timeLeft / 60), s = timeLeft % 60;
            document.getElementById('axom_timer').innerHTML = "Time Left: " + m + ":" + (s < 10 ? '0' : '') + s;
            if (timeLeft <= 0) axomSubmit();
        }, 1000);

        function axomSubmit() {
            clearInterval(timer);
            let score = 0;
            let qData = <?php echo json_encode($qs); ?>;
            qData.forEach(q => {
                let sel = document.querySelector(`input[name="q${q.id}"]:checked`);
                if (sel && sel.value === q.correct) score++;
                else if (sel) score -= 0.25;
            });
            document.getElementById('axom_quiz_area').style.display = 'none';
            document.getElementById('axom_result').style.display = 'block';
            document.getElementById('final_score').innerHTML = "Your Score: " + score;
            
            // Auto Save to DB via AJAX
            let fd = new FormData();
            fd.append('action', 'axom_save_result');
            fd.append('name', document.getElementById('p_name').value);
            fd.append('score', score);
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: fd });
        }
    </script>
    <?php return ob_get_clean();
});

// ================= AJAX SAVE =================
add_action('wp_ajax_axom_save_result', function() {
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'axom_leaderboard', [
        'name' => sanitize_text_field($_POST['name']),
        'score' => floatval($_POST['score'])
    ]);
    wp_die();
});
