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
Work on moving the error handling to PEAR error handling is under way but still needs alot of work.

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

Testing
driver_test.php is the testing suite provided by Metabase
you will need to configure the mysql section of driver_test_config.php to fit your enviornment

MDB_test.php will require that you setup a database and a table with a few rows.
Depending how you name those you will have to change a few things here and there.
If I find the time it would probably be smart to show off the XML schema manager to get rid of this requirement. :-)

MDB_pear_wrapper_test.php makes use of the same DB settings as MDB_test.php but uses the pear wrapper for its tests.

Some thoughts:
This merger is very far along allready. Now is the time to decide what features should end up in MDB
and what should be moved to the wrappers because it may not be needed anymore.
There might be little tricky bugs that were introduced during the reformatting and restructuring process.
I hope I fixed most of them through the help of the Metabase test suite but ...

Roadmap (Active help and code contributions are requested for all of following):
- Move all error handling to the PEAR error handler.
- Change from indirect references to the database objects (keys to the $databases array) in the supporting classes
  (lib, manager, parser) to direct references to the database object to get rid of unecessary functions
- Modularization (loading extended features on demand)
- Making MDB PEAR CS compatible
- PEAR Doc comments and Documentation
- Finish up the Metabase wrapper and PEAR DB wrapper
- Add support for more RDBMS

Credits (never to early for those huh? :-)  ):
I would especially like to thank Manuel Lemos (Author of Metabase) for getting me involved in this and generally being
around to ask questions.
I would also like to thank Tomas Cox and Stig S. Bakken from the PEAR projects for help in
undertstanding PEAR, solving problems and trusting me enough.
Furthermore I would like to thank for Alex Black for being so enthusiastic about this project and offering
binarycloud as a test bed for this project.
Finally Peter Bowyer for starting the discussion that made people pick up this project again after the first versions
of what was then called "metapear" have been ideling without much feedback.
I guess I should also thank DybNet (my company :-)  ) for providing the necessary means to develop this on
company time (actually for the most part my entire life is company time ... so it goes)