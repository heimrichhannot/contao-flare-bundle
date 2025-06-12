---
sidebar_position: 3
---

# Basic Usage

1. Create a new list configuration in the Contao backend under **Layout &rarr; Lists&ensp;<span className="text--muted">FLARE</span>**
2. Each list is an archive of filter elements: Add filters as children to your list configuration
3. Add a list view content element to a page and select the list configuration
4. Add a reader content element to a separate page and select the list configuration
5. Select the reader page in the list configuration
6. Profit!


## Filter Configuration

Each filter element type specifies its own configuration options. The following options are available for all filter types:
- **Title**: A title that should briefly describe the filter and is shown in the backend listings.
- **Type**: The filter element type to use.
- **Intrinsic**: If checked, the filter is always applied to the list view and not visible in the form shown to the user.
- **Published**: If unchecked, the filter is not shown in the form and not applied when filtering the list view.

### What does _Intrinsic_ mean?
- Each filter has an "intrinsic" option, which means that the filter is always applied to the list view and not visible in the form shown to the user.
- A filter that has intrinsic unchecked is shown in the form and can be used by the user to filter the list view.
- Some filters can only be intrinsic, e.g. the "published" filter. Under the hood, these filters do not specify a Symfony FormType.
