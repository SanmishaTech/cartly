# Storage Folder Permissions for Caddy Server

## Problem

When running Cartly with Caddy and PHP-FPM, the PHP process runs as the `www-data` user. The application needs to write to the `storage/` directory for:
- **Logs**: `storage/logs/` (auth, api, error, mail, order, payment logs)
- **Sessions**: `storage/sessions/` (PHP session files)
- **Cache**: `storage/cache/` (application cache)
- **Uploads**: `storage/uploads/` (shop branding, page images)
- **Temp**: `storage/temp/` (temporary files)

If the storage folder doesn't have proper permissions, you'll see errors like:
- "Permission denied" when writing logs
- Session errors
- Upload failures
- Cache write failures

## Current Status

- **PHP-FPM User**: `www-data:www-data`
- **Storage Owner**: Currently `sanjeev:sanjeev`
- **Required**: `www-data` needs write access to `storage/`

## Solution Options

### Option 1: Change Ownership to www-data (Recommended for Production)

This gives PHP-FPM full control over the storage directory:

```bash
# From project root
cd /home/sanjeev/@Learn/cartly

# Change ownership to www-data
sudo chown -R www-data:www-data storage

# Set permissions (775 = owner/group can read/write/execute, others read/execute)
sudo chmod -R 775 storage

# Verify the change
ls -la storage/
```

**Pros**: Simple, secure, follows best practices for web servers
**Cons**: You'll need sudo to manually manage files in storage

### Option 2: Group-Based Permissions (Recommended for Development)

Keep ownership as your user but add `www-data` to a shared group:

```bash
# From project root
cd /home/sanjeev/@Learn/cartly

# Create a shared group (if it doesn't exist)
sudo groupadd -f cartly-dev

# Add your user to the group
sudo usermod -aG cartly-dev sanjeev

# Add www-data to the group
sudo usermod -aG cartly-dev www-data

# Change group ownership to cartly-dev
sudo chgrp -R cartly-dev storage

# Set permissions (775 = owner/group can read/write/execute)
sudo chmod -R 775 storage

# Log out and back in (or use newgrp) for group changes to take effect
newgrp cartly-dev

# Verify
ls -la storage/
```

**Pros**: You can manage files without sudo, good for development
**Cons**: Requires group setup and logout/login

### Option 3: Permissive Permissions (Quick Fix, Less Secure)

Give everyone write access (not recommended for production):

```bash
# From project root
cd /home/sanjeev/@Learn/cartly

# Set permissive permissions
sudo chmod -R 777 storage

# Verify
ls -la storage/
```

**Pros**: Quick fix, no ownership changes
**Cons**: Less secure, not recommended for production

## Verification

After applying permissions, verify they work:

1. **Check ownership and permissions**:
   ```bash
   ls -la storage/
   ```

2. **Test write access** (as www-data):
   ```bash
   sudo -u www-data touch storage/test-write.txt
   sudo -u www-data rm storage/test-write.txt
   ```

3. **Check PHP-FPM can write**:
   - Make a request to your app (e.g., visit the admin login page)
   - Check if logs are being written:
     ```bash
     ls -la storage/logs/
     ```

4. **Test file upload** (if applicable):
   - Try uploading a file through the admin interface
   - Check if it appears in `storage/uploads/`

## Troubleshooting

### If you still get permission errors:

1. **Check PHP-FPM user**:
   ```bash
   grep -E '^user|^group' /etc/php/8.4/fpm/pool.d/www.conf
   ```

2. **Check current permissions**:
   ```bash
   ls -la storage/
   ```

3. **Check if www-data can write**:
   ```bash
   sudo -u www-data touch storage/test.txt && sudo -u www-data rm storage/test.txt
   ```

4. **Check SELinux/AppArmor** (if enabled):
   ```bash
   # Check SELinux status
   getenforce
   
   # If enforcing, you may need to set context
   sudo chcon -R -t httpd_sys_rw_content_t storage/
   ```

### If you need to manually manage storage files:

If you used Option 1 (www-data ownership), you can:

```bash
# Temporarily change ownership to your user
sudo chown -R sanjeev:sanjeev storage/

# Make your changes

# Change back to www-data
sudo chown -R www-data:www-data storage/
```

## Recommended Setup

For **development**: Use Option 2 (group-based permissions)
For **production**: Use Option 1 (www-data ownership)

## Storage Subdirectories

The storage folder contains:
- `cache/` - Application cache
- `logs/` - Application logs (auth, api, error, mail, order, payment)
- `sessions/` - PHP session files
- `temp/` - Temporary files
- `uploads/shops/` - Shop-specific uploads (branding, page images)

All subdirectories inherit permissions from the parent `storage/` directory when using `-R` flag.
