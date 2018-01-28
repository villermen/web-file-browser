# web-file-browser

A flexible PHP web browser I created for my own website ([live example](https://viller.men/browser/)).

## Features
- Displaying of directories, files and webpages (directories with an index file).
- Downloading of ZIP archives of all visible files in a directory, generating and caching the archives on-demand.
- Completely configurable through a single YAML configuration file ([example](config/config.dist.yml)). 

## Installation
To install, run `composer create-project villermen/web-file-browser --no-dev`.
Symlink any web accessible directory to the project's public directory.
Edit `config/config.yml` to adjust the browser's behavior to your needs.

If symlinking is unavailable you can copy the public directory and edit the path in `public/index.php` to point to the right `autoload.php`.
