# WithEnv

WithEnv is an extremely small helper module for executing code with ENV variables. It exists because
I got tired of re-writing or copying over a #with_env helper method for the 131st time.


## Installation

Add this line to your application's Gemfile:

```ruby
gem 'with_env'
```

And then execute:

    $ bundle

Or install it yourself as:

    $ gem install with_env

## Usage

```
include WithEnv

with_env("FOO" => "BAR") do
  `echo $FOO` # => "BAR\n"
  ENV["FOO"] # => "BAR"
end

# The ENV has been restored to what it was before the
# above with_env block, so FOO no longer exists
`echo $FOO` # => "\n"
ENV.has_key?("FOO") # => false
```

## Development

After checking out the repo, run `bin/setup` to install dependencies. Then, run `rake rspec` to run the tests. You can also run `bin/console` for an interactive prompt that will allow you to experiment.

To install this gem onto your local machine, run `bundle exec rake install`. To release a new version, update the version number in `version.rb`, and then run `bundle exec rake release`, which will create a git tag for the version, push git commits and tags, and push the `.gem` file to [rubygems.org](https://rubygems.org).

## Contributing

Bug reports and pull requests are welcome on GitHub at https://github.com/mhs/with_env-rb.


## License

The gem is available as open source under the terms of the [MIT License](http://opensource.org/licenses/MIT).
