Please don't fork this as a public repo.

Getting started
---------------

1. Get PHP up and running. I used [XAMPP](http://www.apachefriends.org/en/xampp.html) on my Mac. XAMPP is also available for Windows and Linux.

2. Clone this repo into your web server's `htdocs` directory. I've pared down the repo to the bare essentials of what the native apps need. There's no MySQL interactions and no authenticated connections to external servers.

3. Make sure your Apache user has write permission to "mobi-lib/cache".

4. Visit http://hostname/mobi-web/api/ to get started. http://hostname/ needs a db connection to work.
