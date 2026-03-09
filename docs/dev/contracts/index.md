# Contracts Reference

Flare uses a contract-based architecture to allow developers to extend its functionality in a modular and type-safe way.

Contracts are grouped into three main categories:

## 1. Filter Element Contracts
- [**`FormTypeOptionsContract`**](./form-type-options-contract.md): Dynamically modify Symfony Form options.
- [**`HydrateFormContract`**](./hydrate-form-contract.md): Pre-populate form fields with data.
- [**`IntrinsicValueContract`**](./intrinsic-value-contract.md): Provide default values for automatic filters.
- [**`RuntimeValueContract`**](./runtime-value-contract.md): Normalize or preprocess user input.

## 2. List Type Contracts
- [**`ConfigureQueryContract`**](./configure-query-contract.md): The core logic for building SQL queries and joins.
- [**`DataContainerContract`**](./data-container-contract.md): Dynamically determine the database table.

## 3. General Contracts
- [**`PaletteContract`**](./palette-contract.md): Define custom DCA palettes for backend configuration.
- [**`IsSupportedContract`**](../contracts/palette-contract.md): Check if a service is compatible with the current environment.
