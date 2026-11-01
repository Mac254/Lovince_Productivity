# Clock

The full-screen focus clock and timer: personal wallpapers and their settings.

- `GET|POST /api/v1/wallpapers`, `GET wallpapers/{id}/image`, `DELETE wallpapers/{id}`. Raster images only, stored privately and served only to their owner.
- Owns the Clock settings (wallpaper, time format, seconds, ticking) and the Focus timer settings.

Depends on Users.
