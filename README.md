# idrinth/webroot

This small package helps managing certificates for an apache2 automatically. It also provides a reasonable catch all page for visitors who misstyped a project. It's for my personal use, but feel free to use or improve it.

## Deployment

Put into `/var/www`, run composer install and then run `php bin/setup.php` to generate the required configuration for your system.

## Setting up vhosts

Connect to mysql and enter the required information. Start with server(hostname of the mashine hosting the (sub-)domain), followed by domain(the top-level domain and it's contact data), then last fill out the virtualhost entry. Leave the name empty for no subdomain. Then run `php bin/cron.php` to generate the webroots and getting the let's encrypt certificated.

## Support

For support please join the [Discord](https://discord.gg/xHSF8CGPTh), if you have bugs or feature wishes please write an issue.
