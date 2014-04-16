# Overview
Gini PHP Framework is a MVC(Model-View-Controller) framework like any other PHP framework. 


## Friendly CLI
Gini provided `gini` command in command-line mode, which heavily inspired by `composer` and `npm`. You may use it to easily create an app with a few commands, no matter CLI or CGI (Web) app.

## Object Oriented
Gini PHP framework was developed under pure PHP5.4 environment with OO support. Well, it is a **MUST HAVE** feature for all current PHP framework.

## Composer Compatible
You might install Gini via Composer, or use Gini independently, or use Composer for 3rd-party modules within Gini framework.

## Those ORM
Build-in ORM model in the framework provided the ability to developers to access database record in OO way. Database SQL layer was completely encapsulated under ORM layer. When developers call the save method of an ORM object, system will automatically create corresponding database table according class declaration other then setting these in config files.

And **Those ORM** provided a interesting function called "those" to make you finish query in natural language. Here is an example: 
```php
$users = those('users')
    ->whose('name')->beginWith('J')
    ->andWhose('father')->isIn(
        those('users')->whose('email')->contains('genee')
    );
```