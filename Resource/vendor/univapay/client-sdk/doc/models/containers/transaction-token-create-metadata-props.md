
# Transaction Token Create Metadata Props

Alias of GenericMetadataValue, retained because this schema name is part of the published SDK surface. Do not narrow it — see GenericMetadataValue for the contract.

## Data Type

`string|null|int|float|bool`

## Cases

| Type |
|  --- |
| `?string` |
| `int` |
| `float` |
| `bool` |
| [`array<?string\|int\|float\|bool>`](../../../doc/models/containers/generic-metadata-array-item.md) |

## ?string

### Initialization Code

#### Example

```php
$value = 'sale';
```

## int

### Initialization Code

#### Example

```php
$value = 10;
```

## float

### Initialization Code

#### Example

```php
$value = 10.5;
```

## bool

### Initialization Code

#### Example

```php
$value = true;
```

## array<?string|int|float|bool>

### Initialization Code

#### Example

```php
$value = [
    'sale',
    'promo'
];
```

