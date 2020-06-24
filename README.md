# Wordpress Democracy, 0.9

Copyright 2019 Alexander Hacker

## Licence

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

## Functions

The software generated here is a plugin for Wordpress. It supports the following functions:

- extended control options of posts
- the possibility to invite and exclude users by all users after revision by higher roles
- exclusion of an administrator from content editing and other functions
- voting via proxy voting
    - for the assignment of roles
    - for setting plugin options
    - reconciliation of internal documents

offers functions for the democratic assignment of roles via proxy voting, content controls by higher roles, the possibility to invite and exclude users by all users after revision by higher roles, as well as exclusion of an administrator from content editing.

see more in the demo video: https://youtu.be/aUoLF-jrC50

## Installation

Follow the installation instructions from the Integreat Github repository
for the cms at https://github.com/Integreat/cms, and the instructions for
the user interface at https://github.com/Integreat/integreat-webapp.

After the successful setup of Integreat, the plugin can simply be installed as
zip file can be added in the backend under Plugins -> Install.

## possible ToDos

- Make plugin independent from Integreat (It's a good software, but the plugin should not be limited to that)
- Make some essential translations
- developing n-eyes principle instead of four-eyes principle
- implement damping factor to proxy voting
- open to more

## Dependencies
The plugin was developed in the context of a Wordpress based instance of the software
"Integreat" realized. In a standard WP environment the plugin "Integreat
Page Revisions" from the Integreat project, the "'Classic-Editor"' of Wordpress,
and the "'User Role Editor"' by Vladimir Garagulya must be installed. To use the
to treat revisions accordingly for visitors to the site, the
React interface can be used by Integreat. Those, as well as the plugin
"Integreat Feedback" from the Integreat project are required to
to realize visitor feedback.
