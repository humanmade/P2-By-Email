<?php if ( $show_title ) : ?>
<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
<?php endif; ?>

<?php the_content(); ?>

<p style="color:grey;"><?php the_author_link(); ?> | <a href="<?php the_permalink(); ?>"><?php echo sprintf( '%s at %s', get_the_time( get_option( 'date_format' ) ), get_the_time( get_option( 'time_format' ) ) ); ?></a></p>