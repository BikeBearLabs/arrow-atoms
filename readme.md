# Arrow Atoms

An experimental WordPress plugin of server-side blocks, controlled using a functional language.

> [!WARNING]
> This plugin is in early development and is **not yet ready for production use**.
>
> ..._However_, there are large sites with real users currently using it. Do ensure you implement proper caching mechanisms & disaster fallback as warned in the [Current Limitations](#current-limitations) section.

## Installation

Download [latest release](https://github.com/BikeBearLabs/arrow-atoms/releases/latest) of the plugin and install it in your WordPress site.

> [!NOTE]
> This plugin currently depends on **Advanced Custom Fields Pro (ACF Pro)**.
>
> It's not per se, a strictly required dependency, but there are functions currently that assume it's there.
>
> Currently, you will need to modify the plugin's header code to remove the `Required dependencies: ` line if you don't have ACF Pro.

## Overview

Arrow Atoms is a plugin mainly for experienced WordPress block developers, looking for a solution for interacting with server-side logic without leaving the blocks paradigm. This includes things like if-else statements, loops, and other control structures, that depend on logic such as post content or ACF fields.

> [!WARNING] > **This plugin is not for everyone**. It is NOT a replacement for PHP. It is a tool for experienced developers to use in specific situations, & does not cover all of the use cases of proper server-side scripting.
>
> Though, if you do intend to go all-in on Arrow Atoms, you are expected to be able to modify it to your needs, specifically, the `evaluate.php` file.
>
> We acknowledge that is this not ideal, & there are plans to make extending the plugin easier in the future. Check out the [Roadmap](#roadmap) section for more information.

## Usage

In code, simply refer to an Arrow Atom using one of the blocks:

```html
<!-- wp:aa/as-text {"of":"->post_content"} /-->
```

Notice that this uses the WordPress block syntax, with the `aa/as-text` block name. This is the block that will evaluate the Arrow Atom. For more information, refer to the [Blocks](#blocks) section.

The `of` attribute is the Arrow Atom to evaluate. In this case, it's `->post_content`, which is a property access returns the post content. For more information, refer to the [Language](#language) section.

## Blocks

TODO: Document all blocks [here](https://github.com/BikeBearLabs/arrow-atoms/blob/main/blocks).

## Language

TODO: Document `->` language [here](https://github.com/BikeBearLabs/arrow-atoms/blob/main/lib/of/evaluate).

## Current Limitations

- **No Caching**: Arrow Atoms are reparsed & reevaluated on every page load. This is a significant performance hit, especially for complex logic. You should implement your own caching mechanism, something like [Litespeed](https://wordpress.org/plugins/litespeed-cache/) or [WP Fastest Cache](https://wordpress.org/plugins/wp-fastest-cache/).
- **No Disaster Fallback**: If Arrow Atoms fail to evaluate, they will stop rendering of the entire page, & the only way to see the stacktrace is with `wp-content/debug.log` (if you have it enabled). This is a "skill issue" as they say, but it's also a limitation of the plugin.
- **No Extensibility**: There currently exists no way to add your own functions to the Arrow Atoms language. This is a planned feature, but it's not there yet. You will need to modify the `evaluate.php` file to add your own functions.

## Roadmap

- [ ] Action for registering custom functions
- [ ] Parse cache (w/ proper AST)
  - Either that, or improve performance some other way
- [ ] Error handling
  - Don't stop rendering on error
  - Show error message in block
- [ ] Better documentation
  - Finish documenting blocks & language
- [ ] `aa/if` block without `aa/if-then`
  - Currently, `aa/if` will render everything within it, regardless of the condition. Only if you use `aa/if-then` within it, will it work as expected)
- [ ] JSON syntax
  - Write arrow atoms using a JSON object instead of a long, single-line string

## License

MIT

