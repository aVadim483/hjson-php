# hjson-php

[Hjson](http://hjson.org), the Human JSON. A configuration file format for humans. Relaxed syntax, fewer mistakes, more comments.

![Hjson Intro](http://hjson.org/hjson1.gif)

```
{
  # specify rate in requests/second (because comments are helpful!)
  rate: 1000

  // prefer c-style comments?
  /* feeling old fashioned? */

  # did you notice that rate doesn't need quotes?
  hey: look ma, no quotes for strings either!

  # best of all
  notice: []
  anything: ?

  # yes, commas are optional!
}
```

This is the alternative PHP implementation of Hjson with decode method only. The primary PHP implementation see 
[hjson/hjson-php](https://github.com/hjson/hjson-php).

For other platforms see [hjson.org](http://hjson.org).

# Usage

```
use avadim\hjson\Hjson;

$obj = Hjson::decode(hjsonText);
$arr = Hjson::decode(hjsonText, true);

```