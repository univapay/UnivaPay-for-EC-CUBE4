
# Generic Metadata Value

Allowed values for metadata properties. Values may be a string, number, boolean, null, or an array of any of the above — but not a nested object; the server rejects metadata whose direct property values are JSON objects.

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

