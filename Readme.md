Darkroom Bot
------------
Image placeholder service to quick and dirty edit a closed image set faster than a teenager on instagram.

# Install

1. Run `composer install` to install dependencies
2. Copy content of app directory to server hosting location
3. Add images to `images` directory on hosting location
4. Make sure `_cache` directory is writeable
5. Profit

Look below for development instructions

# Usage

Navigate to _**/** width **/** height_ in your browser to get a random image in given _width_ or _height_. This can be either an integer to indicate the number of pixels or auto to allow to automatically scale.

If both a _width_ and _height_ are given in pixels the picture will be resized to cover the given area. If the image is larger than the given ratio the excess will be cropped to keep aspect ratio.
 
You can target a specific image to add the `image` query parameter with the value of the image file name without extension.

# Development

You can user the php build in server during development

1. Run `composer install` to install dependencies
2. Navigate to the app directory in terminal
3. Run `php -S 0.0.0.0:8888 darkroom.bot.php`
4. Navigate in your browser to `http://localhost:8888`

Make sure the `images` folder has image files and the `_cache` directory is writeable.