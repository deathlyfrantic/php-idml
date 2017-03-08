## php-idml

A simple class to handle file management of Adobe InDesign IDML files. Keeping track of all the individual files within
an `.idml` package is a chore. This helps a bit. (A *bit*.)

#### Usage

Instantiate the object:

    require_once "IDMLPackage.php";
    $idml = new \IDMLPackage\IDMLPackage("filename.idml");

This will unzip `filename.idml` to a directory called `.filename.idml`. This directory will be deleted when the object
is garbage-collected (see the `__destruct()` method). Alternatively, if you keep your IDMLs stored unzipped, a directory
can also be passed to the constructor:

    $idml = new \IDMLPackage\IDMLPackage("/path/to/idml/");

This directory will not be deleted upon object destruction. (Admittedly this is not a typical use-case but happened to
be how the project I was working on stored things.)

The `IDMLPackge` object is essentially a file manager/server of `DOMDocument` objects for all the internal files. That's
most of what this class does.

    $spreads = $idml->getSpreads();
    $storyu12a = $idml->getStories("u12a");
    $backingStory = $idml->getBackingStory();
    // etc

Generally if you want Whatever, use `getWhatever()`. You likely won't need to use any of the setters but they're there
if you want to do something weird. Note `getSpread()` is a convenience method that gets the first spread, operating
under the assumption that there is only one. This applied to the project from which this class was born, but may not
apply to yours. This method is also used in the `addElementToSpread()` method if a spread is not explicitly
provided. Use caution.

#### Notable Methods

These are just the most generic methods that existed in the project-specific version of this class. I have put zero
effort into trying to make this a generically useful class.

- `getLayers($selfsOnly, $visibleOnly)`: Returns an array of layers from the `designmap.xml` of this IDML. It can
  either return the layer elements (`DOMNodes`) or the self attributes (e.g. "u12a") of the layers. The
  `$visibleOnly` flag determines whether non-visible layers should be included.
- `getElementBySelfAttribute($self)`: Returns the element identified by `$self` from whatever file it lives in. So
  if you're looking for `<Rectangle Self="u12a">` but don't know if it's in a spread or a story, use this. Returns
  false if it fails but I can't imagine you'd be looking for an element by using an arbitrary self attribute. If you do,
  check for false.
- `addStoryToDesignMap($val)`: If you've created a new story, this is a quick method to add your story to the
  package and put it in the `designmap.xml` so InDesign can find it. `$val` needs to be a `DOMDocument`.
- `getMarkupTag($node)`: Get the tag associated with a given element (`DOMNode`) - or an ancestor. It searches every
  element from `$node` up and returns the first tag it finds. This logic may not exactly jibe with how tags are
  intended to work in IDML; it does what I need it to do for my project but may not work for you. The tag it returns
  is `urldecode()`ed.
- `saveAll()`: Save all the files in the IDML. There are individual save methods for saving various pieces but why
  bother? Just use this one.

Everything else you'll just have to figure out on your own.

#### License

MIT
