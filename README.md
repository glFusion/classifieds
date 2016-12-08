# Classified Ad plugin for glFusion
Release 1.1.0

Classifieds is a classified advertising plugin for GLFusion.

## History
- zClassified v.11 2001-12-30 by Alan Zhao <love1001_98@yahoo.com>, http://www.xgra.com
- Geeklog Integration, 2003-08-14
  - Robert Stadnik <geeklog@geeklog.now.pl>, http://geeklog.now.pl
  - Ron Ackerman <ron@miplanet.net>, http://www.lafayetteoh.com
- Adapted for GLFusion 1.0 2008-11-12 with additional modifications by Lee Garner <lee@leegarner.com>

## Changes from the zScripts version

Several key changes have been made to this version:

-   All configuration, except table names and a few non-user-modifiable items,
    is handled through GLFusion's central configuration system.

-   Thumbnails are always automatically generated. Images are always resized
    and are displayed using Slimbox.

-   There are no "custom fields".  A "Price" field has been added.

-   Multiple ad types are supported, e.g. "For Sale", "Wanted", "For Trade", etc.

-   Search is handled by GLFusion's search function. A "keywords" field has
    been added which is used along with the subject and description when
    searching, but is not displayed with the ad.
