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

** Current State:

The current release can be found at the PEAR webpage:
  http://pear.php.net/package-info.php?package=MDB

MDB is still in beta at the moment.

MDB is based on Metabase but has been reworked severely to better match
the PEAR DB API and PEAR CS. Currently there is a MySQL and a PostGreSQL
driver available. The PostGreSQL driver is still missing some management
features (like handling Indexes and reverse engineering of xml schemas).

The approach I have taken so far is to take DB.php from
PEAR DB and make it create a Metabase object. I have changed the
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

A word of warning: Since quite some time my testing has only comprised
of running the two test scripts (see below). Obviously those do not test
everything there is. All the more I encourage people to expand these
tests (especially the MDB_test.php) or just try out some stuff (MDB
should be able to do anything that Metabase can and most of what PEAR
can do, allthough there is no wrapper for PEAR). However, MDB using the
Metabase Wrapper has been running in my companies cvs without any problems
for a couple of weeks now.

** Package Content:

The files that make up MDB are:
  MDB.php
  common.php
  mysql.php
  pgsql.php
  manager_common.php
  manager_mysql.php
  manager_pgsql.php
  lob.php
  manager.php
  parser.php
  metabase_wrapper.php
  pear_wrapper.php

The important pieces for testing right now are:
  driver_test.php (uses driver_test_config.php, setup_test.php,
                   driver_test.schema, log_test.schema, test.schema)
  MDB_test.php (several test calls to MDB's native API)
  MDB_pear_wrapper_test.php (several calls to the MDB PEAR Wrapper)

Other Included Files
  xml_parser.php (this is actually a seperate project of Manuel Lemos
                  that is used for the schema management)
  Var_Dump.php (used in MDB_test.php and MDB_pear_wrapper_test.php
                to display test results)
  Readme.txt (you are reading it currently)

** Documentation:

The entire "public" API and most of the "private" methods (except for some of
the lob classes) have been documented with PHPDoc comments. Most of the API
is borrowed from PEAR DB, so you can look there for documentation. Since there
are a large number of new methods available thanks to the Metabase heritage
of MDB you will also have to take a look in the Metabase documentation
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
and compare it with a corresponding MDB's driver ([DRIVER NAME].php)
This will give a good idea what changes you have to make to an existing
Metabase driver in order to make it MDB compatible.

(Note: In order to get the Metabase code you will need to download it from
phpclasses using the URL provided at the top).

Alot of methods have been renamed from the original Metabase API. The best
place to find the name to what a method has been renamed you should look
at the metabase_wrapper.php file.

Please also note that some methods have been taken from PEAR DB so for some
missing methods you can check the PEAR DB driver.

Another alternative would be to take an MDB driver and hack it to fit
the new RDBMS. I would however recommend working with the existing
Metabase driver for that RDBMS when doing those changes. This will
surely be faster and it will ensure that the new driver takes full
advantage of the MDB framework (which is to large parts based on
Metabase).

In order to test the results driver_test.php will be the most
comprehensive. This test suite uses the Metabase Wrapper.
Therefore, I recommend getting MDB_test.php to work first and after that
worry about driver_test.php. Also keep in mind that you will have to
modify driver_test_config.php before running driver_test.php with the
new driver.

** Some thoughts:

This merger is very far along allready. Now is the time to decide what
features should end up in MDB and what should be moved to the wrappers
because it may not be needed anymore. There might be little tricky bugs
that were introduced during the reformatting and restructuring process.
I hope I fixed most of them through the help of the Metabase test suite
but ...

** Roadmap:

1.0 RC1 (early August)
- API cleanups (only lob.php and manager.php left to cleanup) (lsmith)
- Finish core parts of the pgsql driver (pgc)
- More tests with Metabase and PEAR DB wrapper (you)

1.0 Release (sometime in August)
- API cleanups (only lob.php and manager.php left to cleanup) (lsmith)
- Finish core parts of the pgsql driver (pgc)
- More tests with Metabase and PEAR DB wrapper (you)

1.1 Release (Fall 2002)
- PHPDoc comments (missing in lob.php) (lsmith)
- Further cleanups in lob.php, parser.php (lsmith)
- Add support for more RDBMS (you)
- Finish management parts of the pgsql driver (pgc)

Some time
- Interactive Application for Schema Reverse Engineering to better handle
ambiguities that cannot be resolved automatically
- SQL Funtion Abstraction (for example SUBSTRING() and SUBSTR())

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