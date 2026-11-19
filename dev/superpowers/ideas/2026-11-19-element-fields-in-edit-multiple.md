# Idea: element fields in "Edit multiple"

Not a spec, not a plan — a first idea to come back to.

## Why

The cross-check of branch `v0.2-calendar-columns` (2026-11-19) found that the time-window pickers cannot be edited in
*Edit multiple*: an editor can tick *Field for the start* on the field-selection screen and then gets no widget. The
cause is older than that branch and affects every element type: `Contao/Hook/ElementDcaHook` builds an element's
palette only for the record in the request (`act=edit`, `applyPalette: $isEditAction`), so in `editAll`/`overrideAll`
the element-specific fields (`elm_fieldGeneric`, the period settings, the pickers, …) are in no palette Contao renders.
Contao renders only the fields of each row's palette, and the default palette has none of them. The *Overlap*
checkbox, added by `buildDca()` only when the end picker is set, is missing there for the same reason.

## Shape

- Build the element DCA per row in `editAll`: Contao 5's `DC_Table::editAll()` loads the palette per record
  (`getPalette()` with the current record); the hook would have to provide each type's palette for the record being
  rendered, not only for the request's id.
- Or register every element type's palette statically (by type) once, so `getPalette()` finds it by the row's `type`,
  and keep only the record-dependent parts (date widget per mode, *Overlap* with an end picker) in the per-record path.
- Test with two selected rows of different types, including the time window's recurrence and overlap settings.
