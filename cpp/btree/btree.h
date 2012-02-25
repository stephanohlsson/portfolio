// Stephan Ohlsson
// 3389772
// stephan.ohlsson@gmail.com
// Templated btree

#ifndef BTREE_H
#define BTREE_H

#include <iostream>
#include <cstddef>
#include <utility>
#include "btree_iterator.h"

using std::pair;

template <typename T>
class btree;

template <typename T>
std::ostream& operator<<(std::ostream& os, const btree<T>& tree);

template <typename T> 
class btree {
public:

	friend class btree_iterator<T>;
	friend class const_btree_iterator<T>;
	typedef btree_iterator<T> iterator;
	typedef const_btree_iterator<T> const_iterator;
	typedef std::reverse_iterator<const_iterator> const_reverse_iterator;
	typedef std::reverse_iterator<iterator> reverse_iterator;

	reverse_iterator rbegin() { return reverse_iterator(end()); }
	const_reverse_iterator rbegin() const { return const_reverse_iterator(end()); }
	reverse_iterator rend() { return reverse_iterator(begin()); }
	const_reverse_iterator rend() const { return const_reverse_iterator(begin()); }

	btree(size_t maxNodeElems);
	btree(const btree<T>& original);
	btree<T>& operator=(const btree<T>& rhs);

	friend std::ostream& operator<< <T> (std::ostream& os, const btree<T>& tree);

	const_iterator begin() const;
	const_iterator end() const;
	iterator begin();
	iterator end();

	iterator find(const T& elem);
	const_iterator find(const T& elem) const;
	
	std::pair<iterator, bool> insert(const T& elem);

	~btree();

private:
	/* 
	Basically, each "block" of values is called a node.
	A node has a map of Vals. Vals contains the value type T,
	and a pointer to the left/right children. 
	Vals are created, then inserted into the node.
	Nodes know who their parents are, so we can get around the tree.
	The iterator works by having a pointer to a node, and
	a pointer to a Val (in the form of a map iterator)
	The btree class contains the root Node as part of it.
	
	*/

	struct Val;
	struct Node {
		Node(Node* n = 0) : parent_n(n) {}
		std::map<T, Val> vals_;
		Node* parent_n;
	};
	struct Val {
		Val(Node *l = 0, Node *r = 0) : left(l), right(r) {}
		Node *left;
		Node *right;
		T val;
	};	
	size_t maxNodeElem;
	struct Node root;

	void copy_val(Val&, Node*);
	void copy_node(Node*, Node*);

	std::pair<iterator, bool> insert_new_node (Node* parent, const T& lowerb, const T& elem);
	pair<iterator, bool> insert_new_node_left (Node* parent, const T& lowerb, const T& elem);
	pair<iterator, bool> insert_new_node_right (Node* parent, const T& lowerb, const T& elem);
	pair<iterator, bool> insert_recur (Node* node, const T& elem);
	iterator itr_recur_left(Node* n);
	const_iterator itr_recur_left(Node* n) const;
	iterator find_recur(Node* n, const T& elem);
	const_iterator find_recur(Node* n, const T& elem) const;
	void destroy_val(Val&);
};
#include "btree.tem"

#endif
