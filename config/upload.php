<?php

return [
    // Match PHP upload_max_filesize/post_max_size and Nginx client_max_body_size.
    'max_bytes' => 50 * 1024 * 1024,
    // Keep server-side content checks when forwarding files to cloud storage.
    'direct_upload' => false,
];
