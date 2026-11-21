<?php
/**
 * MY UGC VIDEO — Admin Media Module (Decommissioned)
 * Direct URL access is decommissioned. Returns HTTP 404 Not Found.
 */
http_response_code(404);
header('HTTP/1.1 404 Not Found');
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>404 Not Found</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #001713; color: #D9E5DF; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; text-align: center; }
    h1 { font-size: 3.5rem; margin-bottom: 0.5rem; color: #36D9B0; }
    p { color: #8BAAA2; font-size: 1.1rem; }
  </style>
</head>
<body>
  <div>
    <h1>404</h1>
    <p>The requested admin page does not exist or has been removed.</p>
  </div>
</body>
</html>
<?php exit; ?>
