---
sidebar_position: 3
---

# Basic Usage

Using Flare is simple: define a list, add filters, and display it on your page.

## The 5-Step Setup

1. **Create a List Configuration**: Go to <strong>Layout → Listings&nbsp;&nbsp;<span style={{ opacity: .6 }}>FLARE</span></strong>&ensp;in the Contao backend.
2. **Configure the List**: Select the List Type (e.g., News, Events, Data Container) and configure the relevant fields.
3. **Add Filter Elements**: Each list is an archive of filters. Add filters as children to your list.
4. **Display the List**: Add a **Flare List View** content element to any page and select your list configuration.
5. **Add a Reader (Optional)**: If you need detail pages, add a **Flare Reader** content element to a separate page and select your list configuration.
   - **Reader-URLs**: If you have a detail page, and you want flare to automatically generate URLs to it, return to the list configuration and specify the details page.

---

## Core Concepts

### 1. List Configuration (The "Specification")
A list configuration is the static definition of your data source. It determines:
- Which database table to use.
- Which filter elements are available.
- Default sorting and pagination settings.
- The "auto_item" field used for detail page URLs.

### 2. Interactive Lists
A **Flare List View** content element uses the `InteractiveContext`. This automatically:
- Generates a Symfony filter form.
- Handles pagination and URL parameters.
- Processes user input to filter the list in real-time.

### 3. Detail Pages (Readers)
The **Flare Reader** content element uses the `auto_item` feature to display a single record.
- Flare automatically resolves the record based on the URL alias.
- It provides metadata (Page Title, Description) via events.

### 4. What is an "Intrinsic" Filter?
Every filter has a **Control** setting that decides how the user operates it:
- **Control left blank (intrinsic)**: The filter is applied automatically with its fixed *value* (e.g., `published = 1`)
  and is **hidden** from the user.
- **A control chosen** (checkbox, choice, date range, keywords, ...): The filter is **visible** in the frontend
  form, allowing the user to interact with it. Its *value* is then only the initial value.

---

## Example: News List

1. Create a list of type **News**, which automatically uses the `tl_news` table.
2. Add an **intrinsic** filter (no control), e.g., a published filter.
3. Add a **visible** filter (with a control), e.g., a news archives filter using the archive filter element and the choice control.
4. Add the content element to a page.
5. Flare will show a dropdown of news archives and list only published the news articles belonging to the selected archive.

> In case of the the News list type, a `published` filter is added by default. If you add your own published filter, it will replace the default one.

Besides **News** and the generic **Data Container** type, Flare ships an **Events** list type for
`tl_calendar_events`, which becomes available when the Contao calendar bundle is installed.
See [Integrations](integrations/) for details.
