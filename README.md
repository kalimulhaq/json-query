# A JSON query language for your Laravel/Lumen API

[![Latest Version on Packagist](https://img.shields.io/packagist/v/kalimulhaq/json-query.svg?style=flat-square)](https://packagist.org/packages/kalimulhaq/json-query)
[![Total Downloads](https://img.shields.io/packagist/dt/kalimulhaq/json-query.svg?style=flat-square)](https://packagist.org/packages/kalimulhaq/json-query)

JsonQuery is a query language for your Laravel/Lumen API, which convert JSON query to Laravel Eloquent ORM query. 

## Installation

You can install the package via composer:

```bash
composer require kalimulhaq/json-query
```

## Usage

``` php
use Illuminate\Http\Request;
use Kalimulhaq\JsonQuery\JsonQueryFacade as JsonQuery;

class UsersController extends Controller {

    public function index(Request $request) {
        $this->user = $request->user();
        $json2query = JsonQuery::init('App\User', $request->filter);
        $json2query->buildQuery();
        $json2query->buildResult($request->limit, $request->page);
        $result = $json2query->result();
        $meta = $json2query->meta();
        return response()->json(['data' => $result, 'paginator' => $meta]);
    }
}
```

### Filter
`filter` is a valid JSON string of the following form

``` json
{
  "select": [],
  "where": {},
  "order": [],
  "include": [],
  "include_count": [],
  "scopes": []
}
```

#### Example

``` json
{
  "select": ["id","first_name","last_name","email","phone"],
  "where": {
    "wildcard": {
      "fields": ["first_name","last_name","email","role.name"],
      "value": "any keyword"
    },
    "and": [
      {
        "field": "email",
        "operator": "like",
        "value": "test@email.com"
      },
      {
        "field": "phone",
        "value": "123456789"
      },
      {
        "or": [
          {
            "field": "first_name",
            "operator": "like",
            "value": "kalim"
          },
          {
            "field": "first_name",
            "operator": "like",
            "value": "juli"
          }
        ]
      },
      {
        "field": "role",
        "operator": "where_has",
        "value": {
          "and": [
            {
              "field": "name",
              "value": "admin"
            }
          ]
        }
      }
    ]
  },
  "include": [
    {
      "relation": "role",
      "select": ["id","name"],
      "include": [
        {
          "relation": "permission",
          "select": ["id","name"]
        }
      ]
    }
  ],
  "include_count": [
    {
      "relation": "task"
    }
  ],
  "order": [
    {
      "field": "first_name",
      "order": "asc"
    },
    {
      "field": "last_name",
      "order": "asc"
    }
  ]
}
```
### Select
`select` is an array of columns to select from the base model, If removed `*` will be used

### Where
`where` is a valid json object used to construct the `WHERE` clause 
``` json
{
  "and": [],
  "or": [],
  "field": "",
  "value": "",
  "operator": "",
  "sub_operator": ""
}
```
#### and
to combine the where clause with `AND` 
#### or
to combine the where clause with `OR` 

Both `and` and `or` are arrays of objects, each object is representing one `where` clause 

``` json
{
  "field": "",
  "value": "",
  "operator": "",
  "sub_operator": ""
}
```
If `operator` removed `=` will be used as a default operator 

The (optional) outside `field, value, operator, sub_operator` make a singal where clause which will be combined with `AND` with the `and` and `or` groups.


#### field
column name or relationship name, if operator is `has`, `not_has`, `where_has`, or `where_not_has` the field will be consider is a relationship 

#### value
any type of value to search. if operator is `has`, `not_has`, `where_has`, or `where_not_has` the value will be an object of `where` type 

####  operator 
supported operators are  `=`, `!=`, `<`, `>`, `<=`, `>=`, `between`, `not_between`, `in`, `not_in`, `null`, `not_null`, `date`, `day`, `moth`, `year`, `time`, `like`, `has`, `not_has`, `where_has`, and `where_not_has`

#### sub_operator
sub operator is only required if `operator` is `has` or `not_has`, and supported sub operators are `=`, `!=`, `<`, `>`, `<=`, `>=`


## Order 
Order is used to order the rows. `order` is An array of objects 
``` json
[
    {
      "field": "first_name",
      "order": "asc"
    }
]
```

## Include
In order to includes related models. `include` is an array of objects with same structure of the root `filter` (see above) object with extra property `relation`. 
``` json
{
  "relation":"",
  "select": [],
  "where": {},
  "order": [],
  "include": [],
  "include_count": [],
  "scopes": []
}
```

## Include Count
In order to includes related models count. `include_count` is an array of objects. 
``` json
{
  "relation":"",
  "where": {}
}
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email kalim.dir@gmail.com instead of using the issue tracker.

## Credits

- [Kalim ul Haq](https://github.com/kalimulhaq)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.