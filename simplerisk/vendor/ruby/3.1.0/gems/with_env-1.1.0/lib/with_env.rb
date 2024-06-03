require "with_env/version"

module WithEnv
  extend self

  def with_env(env, &blk)
    before = ENV.to_h.dup
    env.each { |k, v| ENV[k] = v }
    yield
  ensure
    ENV.replace(before)
  end

  def without_env(*keys, &blk)
    before = ENV.to_h.dup
    keys.flatten.each { |k| ENV.delete(k) }
    yield
  ensure
    ENV.replace(before)
  end
end
