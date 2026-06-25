<?php
require __DIR__ . '/../lib/auth.php';

reset_session();
json_out(['success' => true]);
