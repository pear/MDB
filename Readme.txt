** Introduction:

"PEAR MDB" (as I am calling it right now for a lack of better name to
differentiate from the existing projects) is a project to merge PEAR DB
and Metabase into one DB abstraction layer.

You can get info on these at:
  PEAR DB: http://pear.php.net
  Metabase: http://phpclasses.upperdesign.com/browse.html/package/20/

At these URLs you will also find the licensing information on these two
projects along with the credits.

If you have any questions or suggestions you can contact me (Lukas
Smith) at this email address:
  smith@dybnet.de
Co-Author is Christopher Linn:
  clinn@dybnet.de

Or even better post a message to these 3 mailinglists (please include
all of these because there are people on each of these mailinglists that
care about this project and have asked to be included in any
discussion):
  pear-dev@lists.php.net
  metabase-dev@yahoogroups.com
  dev@binarycloud.tigris.org

** Features

MDB provides a common API for all support RDBMS. The main difference to most
other DB abstraction packages is that MDB goes much further to ensure
portability. Among other things MDB features:
* An OO-style query API
* A DSN (data source name) or array format for specifying database servers
* Datatype abstraction and on demand datatype conversion
* Portable error codes
* Sequential and non sequential row fetching as well as bulk fetching
* Ordered array and associative array for the fetched rows
* Prepare/execute (bind) emulation
* Sequence emulation
* Replace emulation
* Limited Subselect emulation
* Row limit support
* Transactions support
* Large Object support
* Index/Unique support
* Extension Framework to load advanced functionality on demand
* Table information interface
* RDBMS management methods (creating, dropping, altering)
* RDBMS independent xml based schema definition management
* Altering of a DB from a changed xml schema
* Reverse engineering of xml schemas from an existing DB (currently only MySQL)
* Full integration into the PEAR Framework
* Wrappers for the PEAR DB and Metabase APIs
* PHPDoc API documentation

Currently supported RDBMS:
MySQL
PostGreSQL
Other soon to follow. 

** Current State:

The current release can be found at the PEAR webpage:
  http://pear.php.net/package-info.php?package=MDB

MDB is still currently in the release cylce for 1.0. Once a release candidate
s found to be stable MDB 1.0 will be released.

Currently there is a MySQL and a PostGreSQL
driver available. The PostGreSQL driver is still missing some management
features (like handling Indexes and reverse engineering of xml schemas).

The core of MDB is very stable since quite sometime, with very few bugs that
had to be fixed or API changes that had to be made. The manager is still in a
bit of flux as the API is still not final and some methods still need to be
split up into smaller methods to improve readability and flexability. Also
new features will be added as needed by the MDB_frontend project.

A word of warning: Since quite some time my testing has only comprised
of running the three test scripts (see below). Obviously those do not test
everything there is. All the more I encourage people to expand these
tests (especially the MDB_test.php) or just try out some stuff (MDB
should be able to do anything that Metabase can and most of what PEAR
can do, allthough there is no wrapper for PEAR). However, MDB using the
Metabase Wrapper has been running in my companies cvs without any problems
for a couple of weeks now.

** Package Content:

The files that make up MDB are:
  MDB.php (core class to include)
  common.php (base database class, included by each driver)
  mysql.php (mysql driver, included by MDB.php))
  pgsql.php (postgresql driver, included by MDB.php)
  manager_common.php (base class for db management methods)
  manager_mysql.php (mysql driver for db management methods)
  manager_pgsql.php (postgresql driver for db management methods)
  lob.php (large object classes, included on demand)
  manager.php (XML schema management class)
  parser.php (XML schema parser, included by manager.php)
  date.php (date conversion methods)
  metabase_wrapper.php (wrapper to mimic the Metabase API)
  pear_wrapper.php (wrapper to mimic the PEAR DB API)
  reverse_engineer_xml_schema.php (really lame script to help with
  dumping and creating from to and from xml schema files)

The important pieces for testing right now are:
  driver_test.php (uses driver_test_config.php, setup_test.php,
                   driver_test.schema, log_test.schema, test.schema)
  MDB_test.php (several test calls to MDB's native API)
  MDB_pear_wrapper_test.php (several calls to the MDB PEAR Wrapper)

Other Included Files
  Var_Dump.php (used in MDB_test.php and MDB_pear_wrapper_test.php
                to display test results)
  xml_schema.xsl (XSL to render xml schema files to HTML)
  xml_schema_documentation.html (This document describe the format of
                                 the xml schema files)
  Readme.txt (you are reading it currently)

** Documentation:
PHPDoc generated documentation can be found at: http://www.dybnet.de/MDB/docs/

The entire "public" API and most of the "private" methods (except for some of
the lob classes) have been documented with PHPDoc comments. Most of the API
is borrowed from PEAR DB, so you can look there for detailed documentation.
Since there are a large number of new methods available thanks to the Metabase
heritage of MDB you will also have to take a look in the Metabase documentation
(which can be found at the URL mentioned above, but does require that
you register with phpclasses). Most of these Metabase functions have
been renamed. Looking at the metabase_wrapper.php file should help finding
the new method name in MDB.

For example ($db being an MDB object):
  $converted_value = MetabaseGetTimestampFieldValue($database, $value);
would now be
  $converted_value = $db->getTimestampValue($value);

If you want to help out with documentation please email me.

** Testing:
You will need to setup the following user, with the right to create new
databases:

username = metapear
password = funky

driver_test.php: Is the testing suite provided by Metabase you will need
to configure the mysql and pgsql section of driver_test_config.php to fit
your enviornment and then select a DB to be tested.

MDB_test.php: Several test calls to MDB's native API.

MDB_pear_wrapper_test.php: Several test calls to MDB's PEAR DB Wrapper.

** How to write new Drivers:

MDB is mostly based in Metabase. The method naming and formatting is
changed to better match the PEAR CS however. Therefore the best starting
point is to first take one of the Metabase drivers (metabase_[DRIVER NAME].php)
and compare it with a corresponding MDB driver ([DRIVER NAME].php and
manager_[DRIVER NAME].php). This will give a good idea what changes you have to
make to an existing Metabase driver in order to make it MDB compatible.

(Note: In order to get the Metabase code you will need to download it from
phpclasses using the URL provided at the top).

Alot of methods have been renamed from the original Metabase API. The best
place to find the name to what a method has been renamed you should look
at the metabase_wrapper.php file.

Please also note that some methods have been taken from PEAR DB so for some
missing methods you can check the PEAR DB driver.

Another alternative would be to take a MDB driver and hack it to fit
the new RDBMS. I would however recommend working with the existing
Metabase driver for that RDBMS when doing those changes. This will
surely be faster and it will ensure that the new driver takes full
advantage of the MDB framework (which is to large parts based on
Metabase).

In order to test the results driver_test.php will be the most
comprehensive. This test suite uses the Metabase Wrapper however. Also
this test suite requires alot of the management method to be in place as well.
Therefore, I recommend getting MDB_test.php to work first and after that
worry about driver_test.php. Also keep in mind that you will have to
modify driver_test_config.php before running driver_test.php with the
new driver.

** Roadmap:
1.0 Release (sometime in August)
- More tests with Metabase and PEAR DB wrapper (you)

1.0.x (as needed)
- Finish PHPDoc comments (lsmith)
- Add support for more RDBMS (you)
- Finish reverse engineering parts of the pgsql driver (pgc)
- Add testing suite (pgc)
  (currently there is only the test suite provided by Metabase)
- Add missing features to the manager needed for the MDB_frontend project
    - Add ability to snyc two databases (structure and/or content)
    - Add ability to dump just one table
    - Improve reservse engineering of existing DB's to xml schema files

1.1 Release (Fall 2002)
- Write Docbook documentation
- Add support for more RDBMS (you)
- Complete testing suite (pgc)
- Store the contents of LOB fields into seperate files when dumping

Sometime
- Interactive Application for Schema Reverse Engineering to better handle
  ambiguities that cannot be resolved automatically
  (moved to seperate project MDB_frontend)
- SQL Funtion Abstraction (for example SUBSTRING() and SUBSTR())
- Different modes: performance, portability

** History

MDB was started after Manuel broad be into the discussion about getting the
features of Metabase into PEAR that was going on (again) in December 2001. He
suggested that I could take on this project. After alot of discussion about
how when and if etc I started development in February 2002.

MDB is based on Metabase but has been reworked severely to better match
the PEAR DB API and PEAR CS. The approach I have taken so far is to take DB.php
from PEAR DB and make it create a Metabase object. I have changed the
Metabase structure slightly. The formatting has been reworked
considerably to better fit the final structure (MDB standalone with a
BC-wrapper for Metabase and PEAR DB), to fit the PEAR CS and to make it
easier for other people to contribute.

The metabase_interface.php was been renamed to metabase_wrapper.php and
now only serves as a wrapper to keep BC with Metabase. A wrapper will
have to be created for PEAR DB as well.

Basically the result is a Metabase that is really close to the PEAR DB
structure. I have also added any missing methods from PEAR DB. Work on
moving the error handling to PEAR error handling is under way but still
needs some work.

** Credits (never to early for those huh? :-)  ):

I would especially like to thank Manuel Lemos (Author of Metabase) for
getting me involved in this and generally being around to ask questions.
I would also like to thank Tomas Cox and Stig S. Bakken from the PEAR
projects for help in undertstanding PEAR, solving problems and trusting
me enough. Paul Cooper for the work on the pgsql driver. Furthermore I
would like to thank Alex Black for being so enthusiastic about this
project and offering binarycloud as a test bed for this project.
Christian Dickmann for being the first to put MDB to some real use,
making MDB use PEAR Error and working on the XML schema manager.

Finally Peter Bowyer for starting the discussion that made people pick
up this project again after the first versions of what was then called
"metapear" have been ideling without much feedback. I guess I should
also thank DybNet (my company :-)  ) for providing the necessary means
to develop this on company time (actually for the most part my entire
life is company time ... so it goes)
