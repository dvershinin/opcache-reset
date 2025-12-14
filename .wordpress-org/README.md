# WordPress.org Assets

This directory contains assets for the WordPress.org plugin directory.

## Required Assets

### Icons
- `icon-128x128.png` - Standard resolution icon (128×128 pixels)
- `icon-256x256.png` - High-DPI/Retina icon (256×256 pixels)

### Banners
- `banner-772x250.png` - Standard resolution banner (772×250 pixels)
- `banner-1544x500.png` - High-DPI/Retina banner (1544×500 pixels)

## Source Files

- `icon.svg` - Vector source for icons
- `banner.svg` - Vector source for banners

## Generating PNG Assets

### Using ImageMagick (recommended)

```bash
cd .wordpress-org

# Generate icons
convert -background none icon.svg -resize 128x128 icon-128x128.png
convert -background none icon.svg -resize 256x256 icon-256x256.png

# Generate banners
convert -background none banner.svg -resize 772x250 banner-772x250.png
convert -background none banner.svg -resize 1544x500 banner-1544x500.png
```

### Using Inkscape

```bash
cd .wordpress-org

# Generate icons
inkscape icon.svg --export-filename=icon-128x128.png -w 128 -h 128
inkscape icon.svg --export-filename=icon-256x256.png -w 256 -h 256

# Generate banners
inkscape banner.svg --export-filename=banner-772x250.png -w 772 -h 250
inkscape banner.svg --export-filename=banner-1544x500.png -w 1544 -h 500
```

### Using rsvg-convert (librsvg)

```bash
cd .wordpress-org

# Generate icons
rsvg-convert -w 128 -h 128 icon.svg > icon-128x128.png
rsvg-convert -w 256 -h 256 icon.svg > icon-256x256.png

# Generate banners
rsvg-convert -w 772 -h 250 banner.svg > banner-772x250.png
rsvg-convert -w 1544 -h 500 banner.svg > banner-1544x500.png
```

## Design Notes

### Color Palette
- **Primary**: PHP-inspired purple gradient (`#8B5CF6` → `#7C3AED` → `#5B21B6`)
- **Background**: Deep indigo (`#1E1B4B` → `#312E81` → `#3730A3`)
- **Accent**: Lightning gold (`#FCD34D` → `#F59E0B`)
- **Text**: White to light indigo (`#FFFFFF` → `#C7D2FE`)

### Icon Concept
The icon combines:
- **Circular refresh arrows** - representing cache reset/refresh
- **Lightning bolt** - representing speed and performance
- **Purple gradient** - PHP brand colors

### Banner Features
- Plugin name with gradient text
- Tagline: "Automatic OPcache management for WordPress"
- Feature pills: Memory Cache, File Cache, Auto Updates, CLI Support
- Subtle PHP elephant silhouette
- Speed lines suggesting performance

## Deployment

These assets are automatically deployed to WordPress.org SVN when publishing a new version. The deployment workflow handles syncing the `.wordpress-org` directory to the `assets/` folder in the SVN repository.

