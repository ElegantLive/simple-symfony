# Learn-symfony.
A simple blog api build with `symfony@4.3`.

Forgive my pool English. 

If you are Symfony beginner, this project may help you, or you can leave message in `issue`

The following functions are mainly realized:

- Identity verification - like login, registration, etc.
- Custom error handling.
- Custom validator.
- Send mail asynchronously.
- `Cors` cross-domain.
- File upload.
- Soft deletion.
- Custom `make:rule`.

Some `api` examples：
 
- `GET /article/list` article pagination
- `GET /article/list/self` article pagination
- `GET /article/{id}` article details
- `POST /article/` publishes article
- `PUT /article/{id}` modify the articles
- `DELETE /article/{id}` remove the article 
- `POST /article/{id}/comment/` article comments
- `POST /article/{id}/comment/{id}/reply/{id}` reply to comments
- ...see more in terminal `bin/console debug:router`

## License

[MIT license](http://opensource.org/licenses/MIT)
