Query simulations (QuerySims) were conceived by Hal Helms (www.halhelms.com) to
remove the design and population of a database from the critical path of
application development.  Using QuerySims a programmer may proceed with the
development of data display code while the DBA is designing the database or
before the database is even started.

When the database is complete, simply change the connect string to point to the
real database and replace the QuerySim text with an SQL select statement that
returns the appropriate columns.

QuerySims are especially useful when following the FLiP methodology for Fusebox
framework application development but you don't have to be using Fusebox or
FLiP to use QuerySims!

Manual Installation:
    Be sure PEAR MDB is available to PHP (PEAR should be in the include path).
    Place querysim.php in the /pear/MDB directory.
    
QuerySim Text Syntax:
    The first line is a comma delimited list of query columns.
    Second (and any subsequent lines) are pipe delimited data used to populate the query.
    An empty data column is denoted by the string 'null' or an empty column.
    The value stored in the record set is actually a zero length string, '', rather than a true NULL.
    A line feed denotes an end-of-line.
    All delimiters may be configured through setOption() or as an array of connect() options.

For instance:
    userID,firstName,lastName,userGroups
    100|Stan|Cox|33
would return a record set with 1 row containing 4 columns of information about Stan Cox.

To access an external file use the following syntax (file path/name is connect string database name):
    querysim:///filename
             ^^^note: *3* forward slashes before file path!
These will also work:
    querysim:///../../../webserver.log
    querysim:///c:/netlogs/webserver.log
                  ^       ^note: windows file delimiters can be forward or back slashes.

When calling an external file the text in query() is ignored.  However, if the
parameter isn't passed at all a warning is raised by PHP. Use an empty string
or a dummy string to prevent a warning from being thrown:
    $conn->query('');
    $conn->query('read from file');

=======================================================================

Usage example:

require_once 'MDB.php';

$dsn = 'querysim';

$conn =& MDB::connect($dsn);
if (MDB::isError($conn)) {
    die ('Cannot connect: '.$conn->getMessage()."\n<br />\n<pre>".$conn->getUserInfo()."\n</pre>\n<br />");
}

$user = $conn->query('
    userID,firstName,lastName,userGroups
    100|Stan|Cox|33
    102|Hal|Helms|22
    103|Bert|Dawson|11
');
if (MDB::isError($user)) {
    die ('Database Error: '.$user->getMessage()."\n<br />\n<pre>".$user->getUserInfo()."\n</pre>\n<br />");
}

printf("Result contains %d rows and %d columns\n<br /><br />\n", $conn->numRows($user), $conn->numCols($user));

//Note that you may return ordered or associative results, as well as specific single rows
while ($row = $conn->fetchInto($user, MDB_FETCHMODE_ASSOC)) {
    printf("%d, %s %s, %s\n<br />\n", $row['userID'], $row['firstName'], $row['lastName'], $row['userGroups']);
}//end while

$conn->freeResult($user);
$conn->disconnect();

=======================================================================

External CSV file usage example (only showing lines that are different than inline example):

$dsn = "querysim:///c:/weblogs/webserver.log";

// notice that we change the dataDelim to a ','
$conn = MDB::connect($dsn, array('persistent'=>true, 'dataDelim'=>','));

$user = $conn->query('read CSV file');

=======================================================================

Resources:
    PEAR              pear.php.net
    PEAR MDB          www.dybnet.de/MDB/docs
                      www.php-mag.net/itr/online_artikel/show.php3?id=283&p=0&nodeid=114
    PEAR DB QuerySim  www.databasejournal.com/features/php/article.php/1470251
    Fusebox/FLiP/     www.fusebox.org
    Fusedoc           www.halhelms.com
                      www.grokfusebox.com
                      www.secretagents.com
    PHP Fusebox       bombusbee.com
    CF QuerySim       www.halhelms.com/index.cfm?fuseaction=newsletters.halsteve&issue=16
    