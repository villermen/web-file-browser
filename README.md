# web-file-browser

A flexible PHP web browser I created for my own website ([live example](https://viller.men/browser/)).
It can show directories, files and webpages (directories with an index), and is completely configurable through a simple YAML configuration file.

## Installation
To install, run `composer create-project villermen/web-file-browser --no-dev`.
Symlink any web accessible directory to the project's public directory.
Edit `config/config.yml` to adjust the browser to your needs.

If symlinking is unavailable you can copy the public directory and edit the path in `public/index.php` to still point to the right `autoload.php`.
