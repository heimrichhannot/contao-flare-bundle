# DC_Multilingual

Information on how to integrate [terminal42/dc_multilingual](https://github.com/terminal42/contao-DC_Multilingual) correctly.

## Tiny Migration for pre-release Test Environments

```sql
UPDATE tl_flare_list
SET dcMultilingual_display = CASE
    WHEN dcMultilingual_display = 'all' THEN 'mutli'
    WHEN dcMultilingual_display = 'translated' THEN 'localized'
    ELSE dcMultilingual_display
END;
```
