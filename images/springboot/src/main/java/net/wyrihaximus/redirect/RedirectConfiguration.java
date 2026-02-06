package net.wyrihaximus.redirect;

import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.http.MediaType;
import org.springframework.web.reactive.function.server.RequestPredicate;
import org.springframework.web.reactive.function.server.RouterFunction;
import org.springframework.web.reactive.function.server.ServerResponse;

import static org.springframework.web.reactive.function.server.RequestPredicates.accept;
import static org.springframework.web.reactive.function.server.RouterFunctions.route;

@Configuration(proxyBeanMethods = false)
public class RedirectConfiguration {
	@Bean
	public RouterFunction<ServerResponse> monoRouterFunction(RedirectHandler redirectHandler) {
		return route()
				.GET("/", redirectHandler::redirect)
				.build();
	}
}
