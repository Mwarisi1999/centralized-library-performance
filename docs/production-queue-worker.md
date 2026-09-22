# Production email queue worker

Staff account invitations are dispatched to the `emails` queue on Laravel's `database` queue connection. The production deployment must keep a dedicated worker running so invitations do not depend on an administrator starting a worker manually.

The deployed worker has these requirements:

- Container name: `timesheet-queue`
- Image: `newsite-php83:latest`
- Restart policy: `unless-stopped`
- Network mode: `host`
- Project mount and working directory: `/var/www/centralized-library-performance`
- Command: `php artisan queue:work database --queue=emails --sleep=3 --tries=3 --timeout=60`

An equivalent persistent container can be created on the production host with:

```bash
docker run -d \
    --name timesheet-queue \
    --restart unless-stopped \
    --network host \
    --volume /var/www/centralized-library-performance:/var/www/centralized-library-performance \
    --workdir /var/www/centralized-library-performance \
    newsite-php83:latest \
    php artisan queue:work database --queue=emails --sleep=3 --tries=3 --timeout=60
```

After deploying application changes, restart the worker so long-running processes load the new code:

```bash
docker restart timesheet-queue
```

The `jobs`, `job_batches`, and `failed_jobs` tables are already supplied by `database/migrations/0001_01_01_000002_create_jobs_table.php`; run the normal production migrations before starting the worker. Keep `APP_URL`, `BREVO_API_KEY`, database credentials, and other environment-specific values in the production `.env`, which is not tracked by Git.

Apache virtual hosts, Let's Encrypt certificates, and private keys remain host-managed infrastructure and do not belong in this repository.
