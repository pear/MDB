Introduction:
"PEAR MDB" (as I am calling it right now for a lack of better name to differentiate from the existing projects)
is a project to merge PEAR DB and Metabase into one DB abstraction layer.

You can get info on these at:
PEAR DB: http://pear.php.net
Metabase: http://phpclasses.upperdesign.com/browse.html/package/20/

At these URLs you will also find the licensing information on these two projects along with the credits.

If you have any questions or suggestions you can contact me (Lukas Smith) at this email address:
smith@dybnet.de
Co-Author is Christopher Linn:
clinn@dybnet.de

Or even better post a message to these 3 mailinglists (please include all of these because there are people
on each of these mailinglists that care about this project and have asked to be included in any discussion):
pear-dev@lists.php.net
metabase-dev@yahoogroups.com
dev@binarycloud.tigris.org

Current State:
Right now I am just working with mysql so this is all that you will find in this package.
The approach I have taken so far is to take DB.php from PEAR DB and make it create a Metabase object.
I have changed the Metabase structure slightly. The formatting has been reworked considerably to better
fit the final structure (MDB standalone with a BC-wrapper for Metabase and PEAR DB), to fit the PEAR CS
and to make it easier for other people to contribute.
A word of warning: Since quite some time my testing has only comprised of running the two test scripts (see below).
Obviously those do not test everything there is. All the more I encourage people to expand these tests (especially
the MDB_test.php) or just try out some stuff (MDB should be able to do anything that Metabase can and most of what
PEAR can do, allthough there is no wrapper for PEAR).

The metabase_interface.php was been renamed to metabase_wrapper.php and now only serves as a wrapper to keep BC
with Metabase. A wrapper will have to be created for PEAR DB as well.

Basically the result is a Metabase that is really close to the PEAR DB structure.
I have also added any missing methods from PEAR DB.
Work on moving the error handling to PEAR error handling is under way but still needs some work.

The files that make up MDB are:
MDB.php
common.php
mysql.php
lob.php
manager.php
parser.php
metabase_wrapper.php
pear_wrapper.php

The important pieces for testing right now are:
driver_test.php (uses driver_test_config.php, setup_test.php, driver_test.schema, log_test.schema, test.schema)
MDB_test.php
MDB_pear_wrapper_test.php

Other Included Files
xml_parser.php (this is actually a seperate project of Manuel Lemos that is used for the schema management)
Var_Dump.php (used in MDB_test.php to display test results)
Readme.txt (you are reading it currently)

Documentation:
There is currently work underway in terms of including phpdoc comments. But this will probably take until early
June to be considered half way complete. Alot of the internal API is borrowed from PEAR DB. Since there are a large
number of new methods available thx to the Metabasebasis of MDB you will also have to take a look in the Metabase
documentation (which can be found at the Url mentioned above, but does require that you register with phpclasses).
Alot of these Metabase functions have been renamed, but this has been marked in the code for most of them.
Some methods have been shortend a bit and those are not all documented in the code, but finding the method should not
be all that hard in these cases.

For example ($db being an MDB object):
$converted_value=MetabaseGetTimestampFieldValue($database, $value)
would now be
$converted_value=$db->getTimestampValue($value)

If you want to help out with documentation please email me.

Testing:
driver_test.php is the testing suite provided by Metabase
you will need to configure the mysql section of driver_test_config.php to fit your enviornment

MDB_test.php will require that you setup the following user, with the right to create new databases:
username = metapear
password = funky


MDB_pear_wrapper_test.php makes use of the same DB settings as MDB_test.php but uses the pear wrapper for its tests.

How to write new Drivers:
MDB is mostly based in Metabase. The method naming and formatting is changed to better match the PEAR CS however.
Therefore the best starting point is to first take the mysql Metabase driver (metabase_mysql.php) and compare it with
MDB's mysql driver (mysql.php) (Note: In order to get the Metabase code you will need to download it from phpclasses using the
URl provided at the top). This will give a good idea what changes you have to make to an existing Metabase driver
in order to make it MDB compatible.

The methods towards the bottom are taken from PEAR DB however. Those have to be copied from the corresponding PEAR DB driver.

Another alternative would be to take the mysql.php and hack it to fit the new RDBMS. I would however recommend working with
the existing Metabase driver for that RDBMS when doing those changes. This will surely be faster and it will ensure that
the new driver takes full advantage of the MDB framework (which is to large parts based on Metabase).

In order to test the results driver_test.php will be the most comprehensive. This test suite uses the Metabase Wrapper however.
Therefore I recommend getting MDB_test.php to work first and after that worry about driver_test.php. Also keep in mind
that you will have to modify driver_test_config.php before running driver_test.php with the new driver.

Some thoughts:
This merger is very far along allready. Now is the time to decide what features should end up in MDB
and what should be moved to the wrappers because it may not be needed anymore.
There might be little tricky bugs that were introduced during the reformatting and restructuring process.
I hope I fixed most of them through the help of the Metabase test suite but ...

Roadmap in order of importance (Active help and code contributions are requested for all of following):
- Move all error handling to the PEAR error handler.
- PEAR Doc comments and Documentation
- More tests with Metabase and PEAR DB wrapper
- Add support for more RDBMS
- Making MDB PEAR CS compatible
Removed from Roadmap:
- Modularization (loading extended features on demand) - performance is fine so why bother

Credits (never to early for those huh? :-)  ):
I would especially like to thank Manuel Lemos (Author of Metabase) for getting me involved in this and generally being
around to ask questions.
I would also like to thank Tomas Cox and Stig S. Bakken from the PEAR projects for help in
undertstanding PEAR, solving problems and trusting me enough.
Furthermore I would like to thank for Alex Black for being so enthusiastic about this project and offering
binarycloud as a test bed for this project.
Christian Dickmann for being the first to put MDB to some real use, making MDB use PEAR Error and working on the XMl schema manager.
Finally Peter Bowyer for starting the discussion that made people pick up this project again after the first versions
of what was then called "metapear" have been ideling without much feedback.
I guess I should also thank DybNet (my company :-)  ) for providing the necessary means to develop this on
company time (actually for the most part my entire life is company time ... so it goes)