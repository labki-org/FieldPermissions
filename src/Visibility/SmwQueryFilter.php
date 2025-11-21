<?php

namespace FieldPermissions\Visibility;

use MediaWiki\Context\RequestContext;
use SMW\DIProperty;
use SMW\DIWikiPage;

class SmwQueryFilter {

    private VisibilityResolver $resolver;
    private PermissionEvaluator $evaluator;

    public function __construct(
        VisibilityResolver $resolver,
        PermissionEvaluator $evaluator
    ) {
        $this->resolver = $resolver;
        $this->evaluator = $evaluator;
    }

    /**
     * Filter Factbox properties.
     *
     * @param DIWikiPage $subject
     * @param array &$properties
     */
    public function filterFactboxProperties( DIWikiPage $subject, array &$properties ) {
        $user = RequestContext::getMain()->getUser();

        wfDebugLog(
            'fieldpermissions',
            "filterFactboxProperties called for page " . $subject->getTitle()->getText()
        );

        foreach ( $properties as $key => $values ) {

            $di = $key instanceof DIProperty ? $key : new DIProperty( (string)$key );

            $prop = $di->getKey();
            $level = $this->resolver->getPropertyLevel( $di );
            $visibleTo = $this->resolver->getPropertyVisibleTo( $di );

            if ( $level === 0 && empty( $visibleTo ) ) {
                continue;
            }

            if ( !$this->evaluator->mayViewProperty( $user, $level, $visibleTo ) ) {
                unset( $properties[$key] );
                wfDebugLog(
                    'fieldpermissions',
                    "Factbox HIDDEN property $prop for user=" . $user->getName()
                );
            }
        }
    }
}
