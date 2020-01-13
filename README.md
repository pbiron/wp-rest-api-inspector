# REST API Inspector #

**Contributors:** [pbiron](https://profiles.wordpress.org/pbiron)  
**Tags:** REST API, list table  
**Requires at least:** 4.6  
**Tested up to:** 5.3.2  
**Stable tag:** 0.1.0  
**License:** GPL-2.0-or-later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

Inspect the REST API routes, endpoints, parameters and properties registered on a site

## Description ##

Adds a `Tools > REST API Inspector` menu that presents information about registered REST API routes, endpoints, etc in various list tables.

### On Screen Help ###

There is skeletal help in the WP `Help` tabs on each screen, so I won't bother to say much here about how to get started and use this plugin: Just go to `Tools > REST API Inspector` and see the "Help" tab.

Note: although it may look like there is only one screen (on that Tools menu), each "type" of data (e.g., routes, endpoints, parameters, properties) is displayed in a list table specific to that data "type"...with their own WP Screen instance.  So, be sure to check the `Help` tabs on each such screen (especially the `Questions` tabs).

Some of the on screen help may be incorrect...as I've added new functionality I haven't always had time to update the help tabs (e.g., "Available Actions" tab on the Routes screen doesn't mention the `Schema` and `Handbook` row actions).  But, hey, this is only version 0.1.0 of the plugin, so you'll just have to figure some things out for yourselves :-)

### Implementation ###

The implementation is *very* preliminary...but basically:

* there is a `WP_REST_API_List_Table` (in the plugin's PHP namespace, but named so that in the unlikely event folks want this in core, things will be easier :-)
    * and various sub-classes of that (e.g., `WP_REST_Routes_List_Table`, `WP_REST_Endpoints_List_Table`, etc)
    * these are in `includes/wp-admin/includes` (again, the directory names were chosen to make moving into core easier if that should ever happen)
    * there are also various `rest-api-routes-inspector.php`, `rest-api-endpoints-inspector.php`, etc in `includes\wp-admin` that serve the same purpose as core's `wp-admin/edit.php`, `wp-admin/users.php`, etc.

* there is a rudimentary `WP_REST_API_Query` class that is loosely based on core's `WP_User_Query`
    * this class is used by `WP_REST_API_List_Table::prepare_items()`, just like the analogous core query classes are used by the various core list tables
    * this class is **GROSSLY** inefficient at this point.  I'll work on that in later revisions
    
I'm 100% sure there are bugs...but hopefully not major ones at this point.

I'm also about 90% sure that:

* I misunderstand some things about the route data returned by [WP_REST_Server::get_routes()](https://developer.wordpress.org/reference/classes/wp_rest_server/get_routes/) (which is where the info that is displayed on all of the screens comes from)
* therefore, some of what is displayed is either `wrong`, `misleading`, `not useful`, etc

If you want to browse the code, most of it is fairly well documented, with ample DocBlocks (some of which have correct information :-).  

## How Can I Help? ##

1. Just try the plugin out!  See if you can find any bugs.
2. Let me know if you have any suggestions for changes in functionality (either additions or changes to current functionality).
3. See the various `Questions` help tabs, and provide feedback on those questions.

And finally, answer the big question: Is this plugin something that is worth continuing to work on?  That is, does it provide useful information for users/developers (or could it in the future with changes)? 

The best way to provide feedback is to open an [Issue](https://github.com/pbiron/rest-api-inspector).

## Installation ##

From your WordPress dashboard

1. Go to _Plugins > Add New_ and click on _Upload Plugin_
2. Upload the zip file
3. Activate the plugin

Also, if you have the awesome [GitHub Updater](https://github.com/afragen/github-updater/) plugin activated, you'll be able to update the plugin when new versions are released, right in the WP Dashboard (or via WP-CLI).

### Build from sources ###

1. clone the global repo to your local machine
2. install node.js and npm ([instructions](https://docs.npmjs.com/downloading-and-installing-node-js-and-npm))
3. install composer ([instructions](https://getcomposer.org/download/))
4. run `npm update`
5. run `composer install`
6. run `grunt build`
    * to build a new release zip
        * run `grunt release`

**Important Note:** The build process is a kind of brittle at this point.  Why?  Because the main composer dependency (a set of "framework" classes I use for writing pllugins) is in a private GitHub repo.  So, best *not* to do the `composer install` (or `composer update`) step above :-)

## Changelog ##

### 0.1.0 ###

* init commit
