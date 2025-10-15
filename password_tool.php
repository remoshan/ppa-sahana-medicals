<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hash Tool - Sahana Medicals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">🔐 Password Hash Generator & Verifier</h3>
                    </div>
                    <div class="card-body">
                        
                        <!-- Verify Existing Hash -->
                        <div class="mb-5">
                            <h4>1. Verify Password Hash</h4>
                            <p class="text-muted">Test if a password matches a hash</p>
                            
                            <?php
                            $verify_result = '';
                            if (isset($_POST['verify'])) {
                                $test_password = $_POST['test_password'];
                                $test_hash = $_POST['test_hash'];
                                
                                if (password_verify($test_password, $test_hash)) {
                                    $verify_result = '<div class="alert alert-success">
                                        ✅ <strong>MATCH!</strong> The password matches the hash.
                                    </div>';
                                } else {
                                    $verify_result = '<div class="alert alert-danger">
                                        ❌ <strong>NO MATCH!</strong> The password does not match the hash.
                                    </div>';
                                }
                            }
                            
                            echo $verify_result;
                            ?>
                            
                            <form method="POST" class="bg-light p-4 rounded">
                                <div class="mb-3">
                                    <label class="form-label">Password to Test:</label>
                                    <input type="text" name="test_password" class="form-control" 
                                           value="secret_password" placeholder="Enter password">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Hash to Verify Against:</label>
                                    <textarea name="test_hash" class="form-control" rows="2" 
                                              placeholder="Paste hash here">$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm</textarea>
                                </div>
                                <button type="submit" name="verify" class="btn btn-primary">
                                    <i class="fas fa-check-circle"></i> Verify Password
                                </button>
                            </form>
                        </div>

                        <hr>

                        <!-- Generate New Hash -->
                        <div class="mb-5">
                            <h4>2. Generate New Password Hash</h4>
                            <p class="text-muted">Create a bcrypt hash for any password</p>
                            
                            <?php
                            $generated_hash = '';
                            if (isset($_POST['generate'])) {
                                $new_password = $_POST['new_password'];
                                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                                
                                $generated_hash = '
                                <div class="alert alert-success">
                                    <h5>✅ Hash Generated Successfully!</h5>
                                    <p><strong>Password:</strong> <code>' . htmlspecialchars($new_password) . '</code></p>
                                    <p><strong>Hash:</strong></p>
                                    <textarea class="form-control" rows="2" readonly onclick="this.select()">' . $hash . '</textarea>
                                    <small class="text-muted">Click the hash to select and copy it</small>
                                </div>
                                
                                <div class="alert alert-info mt-3">
                                    <h6>SQL to Insert Admin User:</h6>
                                    <textarea class="form-control" rows="4" readonly onclick="this.select()">INSERT INTO admin_users (username, email, password, full_name, role, status) 
VALUES (\'yourusername\', \'your@email.com\', \'' . $hash . '\', \'Your Full Name\', \'admin\', \'active\');</textarea>
                                </div>';
                            }
                            
                            echo $generated_hash;
                            ?>
                            
                            <form method="POST" class="bg-light p-4 rounded">
                                <div class="mb-3">
                                    <label class="form-label">New Password:</label>
                                    <input type="text" name="new_password" class="form-control" 
                                           placeholder="Enter password to hash" required>
                                    <small class="text-muted">Enter the password you want to create a hash for</small>
                                </div>
                                <button type="submit" name="generate" class="btn btn-success">
                                    <i class="fas fa-key"></i> Generate Hash
                                </button>
                            </form>
                        </div>

                        <hr>

                        <!-- Quick Reference -->
                        <div>
                            <h4>3. Quick Reference</h4>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Password</th>
                                            <th>Hash</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><code>secret_password</code></td>
                                            <td style="font-size: 0.8em;">
                                                <code>$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm</code>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="copyHash('$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm')">
                                                    Copy
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <hr>

                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle"></i> Security Notes:</h6>
                            <ul class="mb-0">
                                <li>Never share password hashes publicly</li>
                                <li>Always use strong, unique passwords</li>
                                <li>Change default passwords immediately after setup</li>
                                <li>Delete this file after you're done for security</li>
                            </ul>
                        </div>

                    </div>
                    <div class="card-footer">
                        <a href="admin/login.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Admin Login
                        </a>
                        <a href="test_admin_login.php" class="btn btn-info">
                            <i class="fas fa-vial"></i> Run Login Diagnostics
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyHash(hash) {
            navigator.clipboard.writeText(hash).then(function() {
                alert('Hash copied to clipboard!');
            }, function() {
                alert('Failed to copy hash');
            });
        }
    </script>
</body>
</html>

