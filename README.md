Don't use this
==============

 It's not done.



If you're crazy...
------------------

Create a database called test, and grant privileges to a user called 'test' with a password 'test'.

Then execute this:

```bash
bin/tailor 'mysql:host=localhost;dbname=test' test.json
```

test.json should contain something that represents the schema in the test database.

Delete the tables from the test database then execute this:

```bash
bin/tailor test.json 'mysql:host=localhost;dbname=test'
```

... That should restore them. In theory.

