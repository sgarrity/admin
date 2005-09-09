create table adminsubcomponents (
	id serial not null,
	component integer not null
		constraint fk_adminsubcomponents_component references admincomponents(id),
	title varchar(255),
	shortname varchar(50),
	show boolean default false not null,
	displayorder integer default 0,
	primary key(id)
);

-- default sub-components
insert into adminsubcomponents (subcomponentid, component, title, shortname, show, displayorder)
	values (8, 1, 'Login History', 'LoginHistory', true, 0);
